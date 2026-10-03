<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('building_subscriptions', function (Blueprint $table): void {
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('renewed_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();

            $table->index(
                ['building_id', 'expires_at'],
                'building_subscriptions_building_expires_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('building_subscriptions', function (Blueprint $table): void {
            $table->dropIndex('building_subscriptions_building_expires_idx');
            $table->dropColumn([
                'grace_ends_at',
                'renewed_at',
                'suspended_at',
                'cancelled_at',
                'metadata',
            ]);
        });
    }
};
