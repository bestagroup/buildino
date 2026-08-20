<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_payout_requests', function (Blueprint $table): void {
            $table->string('idempotency_key', 120)
                ->nullable()
                ->after('uuid');

            $table->unique(
                ['requested_by', 'idempotency_key'],
                'wpr_requester_idem_uq'
            );
        });

        Schema::table('provider_payout_requests', function (Blueprint $table): void {
            $table->string('idempotency_key', 120)
                ->nullable()
                ->after('uuid');

            $table->unique(
                ['requested_by', 'idempotency_key'],
                'ppr_requester_idem_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::table('provider_payout_requests', function (Blueprint $table): void {
            $table->dropUnique('ppr_requester_idem_uq');
            $table->dropColumn('idempotency_key');
        });

        Schema::table('wallet_payout_requests', function (Blueprint $table): void {
            $table->dropUnique('wpr_requester_idem_uq');
            $table->dropColumn('idempotency_key');
        });
    }
};
