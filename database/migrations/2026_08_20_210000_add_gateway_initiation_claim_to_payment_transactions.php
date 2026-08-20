<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->uuid('initiation_token')
                ->nullable()
                ->after('idempotency_key');

            $table->timestamp('initiating_at')
                ->nullable()
                ->after('initiation_token')
                ->index();

            $table->unsignedInteger('initiation_attempts')
                ->default(0)
                ->after('initiating_at');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropIndex(['initiating_at']);
            $table->dropColumn([
                'initiation_token',
                'initiating_at',
                'initiation_attempts',
            ]);
        });
    }
};
