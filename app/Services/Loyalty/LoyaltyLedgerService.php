<?php

namespace App\Services\Loyalty;

use App\Enums\LoyaltyTransactionType;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyRule;
use App\Models\LoyaltyTransaction;
use App\Models\LoyaltyTransactionAllocation;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LoyaltyLedgerService
{
    public function accountFor(User $user): LoyaltyAccount
    {
        return LoyaltyAccount::query()->firstOrCreate(
            [
                'owner_type' => $user->getMorphClass(),
                'owner_id' => $user->getKey(),
            ],
            [
                'balance' => 0,
            ]
        );
    }

    public function awardForPayment(Payment $payment): int
    {
        $payment->loadMissing('payerUser');

        if (! $payment->payerUser) {
            return 0;
        }

        $rules = LoyaltyRule::query()
            ->where('event_type', 'payment_verified')
            ->where('is_active', true)
            ->where(function ($query) use ($payment): void {
                $query
                    ->whereNull('building_id')
                    ->orWhere('building_id', $payment->building_id);
            })
            ->where(function ($query): void {
                $query
                    ->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->orderBy('id')
            ->get();

        $awarded = 0;

        foreach ($rules as $rule) {
            $configuration = is_array($rule->configuration)
                ? $rule->configuration
                : [];

            $minimum = (int) ($configuration['minimum_amount'] ?? 0);

            if ((int) $payment->amount < $minimum) {
                continue;
            }

            $step = max(0, (int) ($configuration['amount_step'] ?? 0));
            $multiplier = $step > 0
                ? intdiv((int) $payment->amount, $step)
                : 1;
            $points = max(0, $multiplier * (int) $rule->points);
            $maximum = max(0, (int) ($configuration['maximum_points'] ?? 0));

            if ($maximum > 0) {
                $points = min($points, $maximum);
            }

            if ($points === 0) {
                continue;
            }

            $expiresDays = max(0, (int) ($configuration['expires_days'] ?? 0));
            $transaction = $this->earn(
                $payment->payerUser,
                $points,
                "loyalty:payment:{$payment->id}:rule:{$rule->id}:v{$rule->version}",
                $payment,
                $rule,
                "Points for verified payment {$payment->payment_number}",
                $expiresDays > 0 ? now()->addDays($expiresDays) : null,
                [
                    'rule_version' => (int) $rule->version,
                    'rule_configuration' => $configuration,
                    'payment_amount' => (int) $payment->amount,
                    'payment_currency' => $payment->currency,
                ]
            );

            $awarded += max(0, (int) $transaction->points);
        }

        return $awarded;
    }

    public function earn(
        User $user,
        int $points,
        string $idempotencyKey,
        ?Model $reference = null,
        ?LoyaltyRule $rule = null,
        ?string $description = null,
        mixed $expiresAt = null,
        array $metadata = []
    ): LoyaltyTransaction {
        if ($points <= 0) {
            throw ValidationException::withMessages([
                'points' => 'Earned points must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use (
            $description,
            $expiresAt,
            $idempotencyKey,
            $metadata,
            $points,
            $reference,
            $rule,
            $user
        ): LoyaltyTransaction {
            $existing = LoyaltyTransaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            $account = $this->lockedAccount($user);
            $balance = (int) $account->balance + $points;

            $transaction = LoyaltyTransaction::query()->create([
                'loyalty_account_id' => $account->getKey(),
                'loyalty_rule_id' => $rule?->getKey(),
                'type' => LoyaltyTransactionType::Earn,
                'points' => $points,
                'balance_after' => $balance,
                'remaining_points' => $points,
                'idempotency_key' => $idempotencyKey,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'description' => $description,
                'expires_at' => $expiresAt,
                'metadata' => $metadata,
            ]);

            $account->update(['balance' => $balance]);

            return $transaction;
        }, 3);
    }

    public function spend(
        User $user,
        int $points,
        string $idempotencyKey,
        ?Model $reference = null,
        ?string $description = null,
        array $metadata = []
    ): LoyaltyTransaction {
        if ($points <= 0) {
            throw ValidationException::withMessages([
                'points' => 'Spent points must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use (
            $description,
            $idempotencyKey,
            $metadata,
            $points,
            $reference,
            $user
        ): LoyaltyTransaction {
            $existing = LoyaltyTransaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            $account = $this->lockedAccount($user);

            if ((int) $account->balance < $points) {
                throw ValidationException::withMessages([
                    'points' => 'The loyalty account balance is insufficient.',
                ]);
            }

            $sources = LoyaltyTransaction::query()
                ->where('loyalty_account_id', $account->getKey())
                ->where('remaining_points', '>', 0)
                ->where(function ($query): void {
                    $query
                        ->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expires_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ((int) $sources->sum('remaining_points') < $points) {
                throw ValidationException::withMessages([
                    'points' => 'The spendable loyalty balance is insufficient or contains expired points.',
                ]);
            }

            $balance = (int) $account->balance - $points;
            $spend = LoyaltyTransaction::query()->create([
                'loyalty_account_id' => $account->getKey(),
                'type' => LoyaltyTransactionType::Spend,
                'points' => -$points,
                'balance_after' => $balance,
                'remaining_points' => null,
                'idempotency_key' => $idempotencyKey,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'description' => $description,
                'metadata' => $metadata,
            ]);

            $remaining = $points;

            foreach ($sources as $source) {
                if ($remaining === 0) {
                    break;
                }

                $allocated = min(
                    $remaining,
                    (int) $source->remaining_points
                );

                LoyaltyTransactionAllocation::query()->create([
                    'spend_transaction_id' => $spend->getKey(),
                    'earn_transaction_id' => $source->getKey(),
                    'points' => $allocated,
                ]);

                $source->update([
                    'remaining_points' => (int) $source->remaining_points - $allocated,
                ]);

                $remaining -= $allocated;
            }

            $account->update(['balance' => $balance]);

            return $spend;
        }, 3);
    }

    public function refund(
        LoyaltyTransaction $spend,
        string $idempotencyKey,
        ?Model $reference = null,
        ?string $description = null
    ): LoyaltyTransaction {
        return DB::transaction(function () use (
            $description,
            $idempotencyKey,
            $reference,
            $spend
        ): LoyaltyTransaction {
            $existing = LoyaltyTransaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            $spend = LoyaltyTransaction::query()
                ->lockForUpdate()
                ->findOrFail($spend->getKey());

            if (
                $spend->type !== LoyaltyTransactionType::Spend
                || (int) $spend->points >= 0
            ) {
                throw ValidationException::withMessages([
                    'transaction' => 'Only a spend transaction can be refunded.',
                ]);
            }

            $account = LoyaltyAccount::query()
                ->lockForUpdate()
                ->findOrFail($spend->loyalty_account_id);
            $points = abs((int) $spend->points);
            $balance = (int) $account->balance + $points;

            $refund = LoyaltyTransaction::query()->create([
                'loyalty_account_id' => $account->getKey(),
                'type' => LoyaltyTransactionType::Adjust,
                'points' => $points,
                'balance_after' => $balance,
                'remaining_points' => $points,
                'idempotency_key' => $idempotencyKey,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'reversed_transaction_id' => $spend->getKey(),
                'description' => $description,
                'metadata' => [
                    'reason' => 'reward_claim_rejected',
                ],
            ]);

            $account->update(['balance' => $balance]);

            return $refund;
        }, 3);
    }

    public function expireDue(): int
    {
        $ids = LoyaltyTransaction::query()
            ->where('remaining_points', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->pluck('id');
        $expired = 0;

        foreach ($ids as $id) {
            $expired += DB::transaction(function () use ($id): int {
                $source = LoyaltyTransaction::query()
                    ->lockForUpdate()
                    ->find($id);

                if (! $source || (int) $source->remaining_points <= 0) {
                    return 0;
                }

                $account = LoyaltyAccount::query()
                    ->lockForUpdate()
                    ->findOrFail($source->loyalty_account_id);
                $points = min(
                    (int) $source->remaining_points,
                    max(0, (int) $account->balance)
                );

                if ($points === 0) {
                    $source->update(['remaining_points' => 0]);

                    return 0;
                }

                LoyaltyTransaction::query()->firstOrCreate(
                    [
                        'idempotency_key' => "loyalty:expire:{$source->id}",
                    ],
                    [
                        'loyalty_account_id' => $account->getKey(),
                        'type' => LoyaltyTransactionType::Expire,
                        'points' => -$points,
                        'balance_after' => (int) $account->balance - $points,
                        'remaining_points' => null,
                        'reversed_transaction_id' => $source->getKey(),
                        'description' => "Expired loyalty transaction {$source->id}",
                    ]
                );

                $source->update(['remaining_points' => 0]);
                $account->decrement('balance', $points);

                return $points;
            }, 3);
        }

        return $expired;
    }

    private function lockedAccount(User $user): LoyaltyAccount
    {
        $account = $this->accountFor($user);

        return LoyaltyAccount::query()
            ->lockForUpdate()
            ->findOrFail($account->getKey());
    }
}
