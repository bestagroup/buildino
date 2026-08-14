<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_wallet_payments', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('facility_reservation_id')
                ->unique()
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('wallet_transfer_id')
                ->unique()
                ->constrained('wallet_transfers')
                ->restrictOnDelete();

            $table->foreignId('source_wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();

            $table->foreignId('building_wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();

            $table->string('payer_source', 30);
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('paid')->index();
            $table->timestamp('paid_at');

            $table->timestamps();
        });

        Schema::table('reservation_cancellations', function (Blueprint $table): void {
            $table->foreignId('refund_wallet_transfer_id')
                ->nullable()
                ->after('refund_payment_id');

            $table->foreign(
                'refund_wallet_transfer_id',
                'rc_refund_wallet_fk'
            )
                ->references('id')
                ->on('wallet_transfers')
                ->nullOnDelete();
        });

        Schema::create('building_bank_accounts', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('building_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('bank_name')->nullable();
            $table->string('account_holder_name');
            $table->string('iban', 34);
            $table->string('account_number', 50)->nullable();
            $table->string('card_number', 19)->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(true);

            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['building_id', 'iban'],
                'bba_building_iban_uq'
            );
        });

        Schema::create('wallet_payout_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('building_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();

            $table->foreignId('building_bank_account_id');

            $table->foreign(
                'building_bank_account_id',
                'wpr_bank_account_fk'
            )
                ->references('id')
                ->on('building_bank_accounts')
                ->restrictOnDelete();

            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('fee_amount')->default(0);
            $table->unsignedBigInteger('net_amount');

            $table->string('status', 20)->default('pending')->index();

            $table->foreignId('requested_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('paid_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('wallet_transfer_id')
                ->nullable()
                ->unique()
                ->constrained('wallet_transfers')
                ->nullOnDelete();

            $table->string('bank_reference')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(
                ['building_id', 'status'],
                'wpr_building_status_idx'
            );
        });

        Schema::create('building_bill_payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('building_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();

            $table->string('bill_type', 30);
            $table->string('bill_identifier', 100)->nullable();
            $table->string('payment_identifier', 100)->nullable();

            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('pending')->index();

            $table->foreignId('requested_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('completed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('wallet_transfer_id')
                ->nullable()
                ->unique()
                ->constrained('wallet_transfers')
                ->nullOnDelete();

            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->json('provider_payload')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            $table->index(
                ['building_id', 'status'],
                'bbp_building_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('building_bill_payments');
        Schema::dropIfExists('wallet_payout_requests');
        Schema::dropIfExists('building_bank_accounts');

        $this->dropRefundWalletTransferColumn();

        Schema::dropIfExists('reservation_wallet_payments');
    }

    private function dropRefundWalletTransferColumn(): void
    {
        if (! Schema::hasColumn(
            'reservation_cancellations',
            'refund_wallet_transfer_id'
        )) {
            return;
        }

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table(
                'reservation_cancellations',
                function (Blueprint $table): void {
                    $table->dropForeign(
                        'rc_refund_wallet_fk'
                    );

                    $table->dropColumn(
                        'refund_wallet_transfer_id'
                    );
                }
            );

            return;
        }

        /*
         * SQLite cannot drop a foreign key constraint by name, and
         * ALTER TABLE DROP COLUMN also fails while the column remains
         * part of a foreign-key definition. Rebuild the table using its
         * schema from the migration that originally created it.
         */
        Schema::dropIfExists(
            'reservation_cancellations_rollback_tmp'
        );

        Schema::create(
            'reservation_cancellations_rollback_tmp',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'facility_reservation_id'
                )
                    ->constrained()
                    ->restrictOnDelete();

                $table->foreignId(
                    'cancelled_by'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->text('reason')
                    ->nullable();

                $table->unsignedBigInteger(
                    'cancellation_fee'
                )
                    ->default(0);

                $table->unsignedBigInteger(
                    'refund_amount'
                )
                    ->default(0);

                $table->string(
                    'refund_status',
                    20
                )
                    ->nullable();

                $table->foreignId(
                    'refund_payment_id'
                )
                    ->nullable()
                    ->constrained('payments')
                    ->nullOnDelete();

                $table->timestamp(
                    'cancelled_at'
                );

                $table->timestamps();
            }
        );

        $columns = [
            'id',
            'facility_reservation_id',
            'cancelled_by',
            'reason',
            'cancellation_fee',
            'refund_amount',
            'refund_status',
            'refund_payment_id',
            'cancelled_at',
            'created_at',
            'updated_at',
        ];

        DB::table(
            'reservation_cancellations_rollback_tmp'
        )->insertUsing(
            $columns,
            DB::table(
                'reservation_cancellations'
            )->select($columns)
        );

        Schema::drop(
            'reservation_cancellations'
        );

        Schema::rename(
            'reservation_cancellations_rollback_tmp',
            'reservation_cancellations'
        );
    }
};
