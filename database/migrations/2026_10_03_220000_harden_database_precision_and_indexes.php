<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->alignNumericPrecision();
        $this->normalizePaymentAllocations();
        $this->dropRedundantIndexes();
        $this->addOperationalIndexes();
    }

    public function down(): void
    {
        $this->dropIndex('payment_allocations', 'payment_alloc_target_uq', true);

        foreach ($this->indexes() as $table => $definitions) {
            foreach ($definitions as [$columns, $name]) {
                $this->dropIndex($table, $name);
            }
        }

        /*
         * Precision upgrades are intentionally not narrowed during rollback:
         * converting DECIMAL values back to INTEGER would destroy valid data.
         */
    }

    private function alignNumericPrecision(): void
    {
        Schema::table('buildings', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable()->change();
            $table->decimal('longitude', 10, 7)->nullable()->change();
        });

        Schema::table('units', function (Blueprint $table): void {
            $table->decimal('area', 12, 2)->nullable()->change();
        });

        Schema::table('storage_units', function (Blueprint $table): void {
            $table->decimal('area', 12, 2)->nullable()->change();
        });

        Schema::table('unit_ownerships', function (Blueprint $table): void {
            $table->decimal('ownership_percentage', 5, 2)->nullable()->change();
        });

        Schema::table('charge_calculations', function (Blueprint $table): void {
            $table->decimal('base_value', 18, 4)->nullable()->change();
        });
    }

    private function normalizePaymentAllocations(): void
    {
        if (! Schema::hasTable('payment_allocations')) {
            return;
        }

        /*
         * Older releases allowed more than one row for the same payment and
         * payable. Consolidate such rows before adding the database invariant.
         */
        $duplicates = DB::table('payment_allocations')
            ->select([
                'payment_id',
                'payable_type',
                'payable_id',
                DB::raw('MIN(id) AS keep_id'),
                DB::raw('SUM(amount) AS total_amount'),
                DB::raw('COUNT(*) AS rows_count'),
            ])
            ->groupBy([
                'payment_id',
                'payable_type',
                'payable_id',
            ])
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('payment_allocations')
                ->where('id', $duplicate->keep_id)
                ->update([
                    'amount' => $duplicate->total_amount,
                    'updated_at' => now(),
                ]);

            DB::table('payment_allocations')
                ->where('payment_id', $duplicate->payment_id)
                ->where('payable_type', $duplicate->payable_type)
                ->where('payable_id', $duplicate->payable_id)
                ->where('id', '<>', $duplicate->keep_id)
                ->delete();
        }

        if (! Schema::hasIndex('payment_allocations', 'payment_alloc_target_uq')) {
            Schema::table('payment_allocations', function (Blueprint $table): void {
                $table->unique(
                    ['payment_id', 'payable_type', 'payable_id'],
                    'payment_alloc_target_uq'
                );
            });
        }
    }

    private function dropRedundantIndexes(): void
    {
        if (
            Schema::hasTable('users')
            && Schema::hasIndex('users', 'users_mobile_email_national_code_index')
        ) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex('users_mobile_email_national_code_index');
            });
        }
    }

    private function addOperationalIndexes(): void
    {
        foreach ($this->indexes() as $table => $definitions) {
            foreach ($definitions as [$columns, $name]) {
                if (
                    ! Schema::hasTable($table)
                    || Schema::hasIndex($table, $name)
                ) {
                    continue;
                }

                Schema::table(
                    $table,
                    fn (Blueprint $blueprint) => $blueprint->index($columns, $name)
                );
            }
        }
    }

    /**
     * @return array<string, array<int, array{0: array<int, string>, 1: string}>>
     */
    private function indexes(): array
    {
        return [
            'otp_codes' => [
                [
                    ['identifier', 'channel', 'purpose', 'consumed_at', 'id'],
                    'otp_lookup_idx',
                ],
            ],
            'user_role_assignments' => [
                [
                    ['user_id', 'is_active', 'starts_at', 'ends_at'],
                    'ura_user_active_window_idx',
                ],
                [
                    ['scope_type', 'scope_id', 'is_active', 'starts_at', 'ends_at'],
                    'ura_scope_active_window_idx',
                ],
            ],
            'unit_ownerships' => [
                [
                    ['user_id', 'is_active', 'starts_at', 'ends_at'],
                    'uo_user_active_window_idx',
                ],
                [
                    ['unit_id', 'is_active', 'starts_at', 'ends_at'],
                    'uo_unit_active_window_idx',
                ],
            ],
            'unit_occupancies' => [
                [
                    ['user_id', 'is_active', 'starts_at', 'ends_at'],
                    'uoc_user_active_window_idx',
                ],
                [
                    ['unit_id', 'is_active', 'starts_at', 'ends_at'],
                    'uoc_unit_active_window_idx',
                ],
            ],
            'unit_parking_assignments' => [
                [
                    ['parking_space_id', 'starts_at', 'ends_at'],
                    'upa_parking_window_idx',
                ],
                [
                    ['unit_id', 'starts_at', 'ends_at'],
                    'upa_unit_window_idx',
                ],
            ],
            'unit_storage_assignments' => [
                [
                    ['storage_unit_id', 'starts_at', 'ends_at'],
                    'usa_storage_window_idx',
                ],
                [
                    ['unit_id', 'starts_at', 'ends_at'],
                    'usa_unit_window_idx',
                ],
            ],
            'building_subscriptions' => [
                [
                    ['building_id', 'status', 'starts_at'],
                    'bs_building_status_start_idx',
                ],
            ],
            'financial_accounts' => [
                [
                    ['building_id', 'is_active', 'type'],
                    'fa_building_active_type_idx',
                ],
            ],
            'funds' => [
                [
                    ['building_id', 'is_active'],
                    'fund_building_active_idx',
                ],
            ],
            'charge_formulas' => [
                [
                    ['building_id', 'is_active'],
                    'cf_building_active_idx',
                ],
            ],
            'charge_periods' => [
                [
                    ['building_id', 'status', 'period_start'],
                    'cp_building_status_start_idx',
                ],
            ],
            'unit_invoices' => [
                [
                    ['building_id', 'issue_date', 'status'],
                    'ui_building_issue_status_idx',
                ],
            ],
            'invoice_installments' => [
                [
                    ['status', 'due_date'],
                    'ii_status_due_idx',
                ],
                [
                    ['unit_invoice_id', 'status', 'due_date', 'installment_number'],
                    'ii_invoice_status_due_idx',
                ],
            ],
            'building_bill_payments' => [
                [
                    ['building_id', 'status', 'completed_at'],
                    'bbp_building_status_completed_idx',
                ],
            ],
            'wallet_payout_requests' => [
                [
                    ['building_id', 'status', 'paid_at'],
                    'wpr_building_status_paid_idx',
                ],
            ],
            'service_requests' => [
                [
                    ['building_id', 'created_at'],
                    'sr_building_created_idx',
                ],
            ],
        ];
    }

    private function dropIndex(
        string $table,
        string $name,
        bool $unique = false
    ): void {
        if (
            ! Schema::hasTable($table)
            || ! Schema::hasIndex($table, $name)
        ) {
            return;
        }

        Schema::table(
            $table,
            function (Blueprint $blueprint) use ($name, $unique): void {
                if ($unique) {
                    $blueprint->dropUnique($name);

                    return;
                }

                $blueprint->dropIndex($name);
            }
        );
    }
};
