<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'building_wallet_accounting_profiles',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('building_id');

                $table->foreignId(
                    'wallet_asset_account_id'
                );

                $table->foreignId(
                    'charge_collection_credit_account_id'
                );

                $table->foreignId(
                    'facility_income_account_id'
                );

                $table->foreignId(
                    'bill_expense_account_id'
                );

                $table->foreignId(
                    'bank_clearing_account_id'
                );

                $table->boolean('is_active')
                    ->default(true);

                $table->timestamps();

                /*
                 * Explicit short index/constraint names are required
                 * because MySQL limits identifiers to 64 characters.
                 */
                $table->unique(
                    'building_id',
                    'bwap_building_uq'
                );

                $table->foreign(
                    'building_id',
                    'bwap_building_fk'
                )
                    ->references('id')
                    ->on('buildings')
                    ->cascadeOnDelete();

                $table->foreign(
                    'wallet_asset_account_id',
                    'bwap_wallet_asset_fk'
                )
                    ->references('id')
                    ->on('financial_accounts')
                    ->restrictOnDelete();

                $table->foreign(
                    'charge_collection_credit_account_id',
                    'bwap_charge_credit_fk'
                )
                    ->references('id')
                    ->on('financial_accounts')
                    ->restrictOnDelete();

                $table->foreign(
                    'facility_income_account_id',
                    'bwap_facility_income_fk'
                )
                    ->references('id')
                    ->on('financial_accounts')
                    ->restrictOnDelete();

                $table->foreign(
                    'bill_expense_account_id',
                    'bwap_bill_expense_fk'
                )
                    ->references('id')
                    ->on('financial_accounts')
                    ->restrictOnDelete();

                $table->foreign(
                    'bank_clearing_account_id',
                    'bwap_bank_clearing_fk'
                )
                    ->references('id')
                    ->on('financial_accounts')
                    ->restrictOnDelete();
            }
        );

        Schema::create(
            'wallet_accounting_postings',
            function (Blueprint $table): void {
                $table->id();

                $table->uuid('uuid');

                $table->foreignId(
                    'wallet_transfer_id'
                );

                $table->foreignId(
                    'building_id'
                )->nullable();

                $table->foreignId(
                    'financial_transaction_id'
                )->nullable();

                $table->string('status', 20)
                    ->default('pending')
                    ->index();

                $table->string('mapping_key', 60)
                    ->nullable()
                    ->index();

                $table->text('reason')
                    ->nullable();

                $table->json('mapping_snapshot')
                    ->nullable();

                $table->unsignedInteger('attempts')
                    ->default(0);

                $table->text('last_error')
                    ->nullable();

                $table->timestamp('posted_at')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    'uuid',
                    'wap_uuid_uq'
                );

                $table->unique(
                    'wallet_transfer_id',
                    'wap_transfer_uq'
                );

                $table->unique(
                    'financial_transaction_id',
                    'wap_fin_tx_uq'
                );

                $table->foreign(
                    'wallet_transfer_id',
                    'wap_transfer_fk'
                )
                    ->references('id')
                    ->on('wallet_transfers')
                    ->cascadeOnDelete();

                $table->foreign(
                    'building_id',
                    'wap_building_fk'
                )
                    ->references('id')
                    ->on('buildings')
                    ->nullOnDelete();

                $table->foreign(
                    'financial_transaction_id',
                    'wap_fin_tx_fk'
                )
                    ->references('id')
                    ->on('financial_transactions')
                    ->nullOnDelete();

                $table->index(
                    ['building_id', 'status'],
                    'wap_building_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'wallet_accounting_postings'
        );

        Schema::dropIfExists(
            'building_wallet_accounting_profiles'
        );
    }
};
