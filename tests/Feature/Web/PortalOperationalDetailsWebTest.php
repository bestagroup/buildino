<?php

namespace Tests\Feature\Web;

use App\Enums\InvoiceStatus;
use App\Enums\InstallmentStatus;
use App\Models\Unit;
use App\Models\UnitInvoice;
use App\Models\User;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalOperationalDetailsWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_operation_indexes_render_server_side_datatables(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $owner =
            $this->user(
                'role.owner@buildino.local'
            );

        $this->actingAs(
            $owner,
            'web'
        );

        foreach (
            [
                'invoices',
                'reservations',
                'guests',
                'services',
                'support',
                'wallet',
            ]
            as $resource
        ) {
            $this->get(
                route(
                    'portal.resident.operations.index',
                    [
                        'resource' =>
                            $resource,
                    ]
                )
            )
                ->assertOk()
                ->assertSee(
                    'js-server-datatable',
                    false
                );
        }
    }

    public function test_resident_can_open_own_invoice_detail_but_not_other_unit_invoice(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $owner =
            $this->user(
                'role.owner@buildino.local'
            );

        $ownerInvoice =
            $this->invoice(
                $this->unit(
                    '101'
                ),
                'DETAIL-OWNER-001',
                $owner
            );

        $otherInvoice =
            $this->invoice(
                $this->unit(
                    '102'
                ),
                'DETAIL-OTHER-001',
                $owner
            );

        $ownerInvoice->invoiceInstallments()->create([
            'installment_number' => 1,
            'due_date' => now()->addDays(5)->toDateString(),
            'amount' => 1_000_000,
            'paid_amount' => 0,
            'status' => InstallmentStatus::Pending,
        ]);

        $this->actingAs(
            $owner,
            'web'
        );

        $this->get(
            route(
                'portal.resident.operations.show',
                [
                    'resource' =>
                        'invoices',
                    'id' =>
                        $ownerInvoice->getKey(),
                ]
            )
        )
            ->assertOk()
            ->assertSee(
                'DETAIL-OWNER-001'
            )
            ->assertSee(
                'اقلام صورتحساب'
            )
            ->assertSee(
                'برنامه اقساط'
            );

        $this->get(
            route(
                'portal.resident.operations.show',
                [
                    'resource' =>
                        'invoices',
                    'id' =>
                        $otherInvoice->getKey(),
                ]
            )
        )->assertNotFound();
    }

    public function test_provider_operation_indexes_are_available_only_in_provider_scope(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $provider =
            $this->user(
                'role.provider@buildino.local'
            );

        $owner =
            $this->user(
                'role.owner@buildino.local'
            );

        $this->actingAs(
            $provider,
            'web'
        );

        foreach (
            [
                'services',
                'payouts',
                'wallet',
            ]
            as $resource
        ) {
            $this->get(
                route(
                    'portal.provider.operations.index',
                    [
                        'resource' =>
                            $resource,
                    ]
                )
            )
                ->assertOk()
                ->assertSee(
                    'js-server-datatable',
                    false
                );
        }

        $this->actingAs(
            $owner,
            'web'
        );

        $this->get(
            route(
                'portal.provider.operations.index',
                [
                    'resource' =>
                        'services',
                ]
            )
        )->assertForbidden();
    }

    private function invoice(
        Unit $unit,
        string $number,
        User $creator
    ): UnitInvoice {
        $unit->loadMissing(
            'floor.block.building'
        );

        return UnitInvoice::query()
            ->create([
                'building_id' =>
                    $unit
                        ->floor
                        ->block
                        ->building
                        ->getKey(),

                'unit_id' =>
                    $unit->getKey(),

                'invoice_number' =>
                    $number,

                'issue_date' =>
                    now()->toDateString(),

                'due_date' =>
                    now()
                        ->addDays(5)
                        ->toDateString(),

                'subtotal' =>
                    1_000_000,

                'total_amount' =>
                    1_000_000,

                'paid_amount' =>
                    0,

                'outstanding_amount' =>
                    1_000_000,

                'status' =>
                    InvoiceStatus::Issued,

                'created_by' =>
                    $creator->getKey(),
            ]);
    }

    private function user(
        string $email
    ): User {
        return User::query()
            ->where(
                'email',
                $email
            )
            ->firstOrFail();
    }

    private function unit(
        string $number
    ): Unit {
        return Unit::query()
            ->where(
                'unit_number',
                $number
            )
            ->firstOrFail();
    }
}
