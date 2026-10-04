<?php

namespace Tests\Feature\Financial;

use App\Models\LoyaltyAccount;
use App\Services\Loyalty\LoyaltyLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class LoyaltyIdempotencyHardeningTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_earn_idempotency_key_cannot_cross_users_or_amounts(): void
    {
        $first = $this->createUser();
        $second = $this->createUser();

        $ledger = app(LoyaltyLedgerService::class);

        $transaction = $ledger->earn(
            $first,
            10,
            'loyalty-semantic-key'
        );

        $this->assertSame(
            10,
            (int) $transaction->points
        );

        try {
            $ledger->earn(
                $first,
                11,
                'loyalty-semantic-key'
            );

            $this->fail(
                'Idempotency key reuse with a different point amount was accepted.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'idempotency_key',
                $exception->errors()
            );
        }

        try {
            $ledger->earn(
                $second,
                10,
                'loyalty-semantic-key'
            );

            $this->fail(
                'Idempotency key reuse for another user was accepted.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'idempotency_key',
                $exception->errors()
            );
        }

        $this->assertSame(
            10,
            (int) LoyaltyAccount::query()
                ->where(
                    'owner_id',
                    $first->id
                )
                ->value('balance')
        );

        $this->assertSame(
            0,
            (int) LoyaltyAccount::query()
                ->where(
                    'owner_id',
                    $second->id
                )
                ->value('balance')
        );
    }

    public function test_spend_idempotency_key_is_bound_to_spend_semantics(): void
    {
        $user = $this->createUser();
        $ledger = app(LoyaltyLedgerService::class);

        $ledger->earn(
            $user,
            20,
            'loyalty-earn-seed'
        );

        $ledger->spend(
            $user,
            5,
            'loyalty-spend-key'
        );

        try {
            $ledger->spend(
                $user,
                6,
                'loyalty-spend-key'
            );

            $this->fail(
                'Spend idempotency key was reused with different semantics.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'idempotency_key',
                $exception->errors()
            );
        }

        $this->assertSame(
            15,
            (int) LoyaltyAccount::query()
                ->where(
                    'owner_id',
                    $user->id
                )
                ->value('balance')
        );
    }
}
