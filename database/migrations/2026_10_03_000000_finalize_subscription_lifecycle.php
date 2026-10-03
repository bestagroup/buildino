<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('building_subscriptions', function (Blueprint $table): void {
            $table->timestamp('grace_ends_at')
                ->nullable()
                ->after('expires_at')
                ->index();

            $table->timestamp('cancelled_at')
                ->nullable()
                ->after('status');

            $table->foreignId('updated_by')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(
                ['building_id', 'starts_at', 'expires_at'],
                'building_subscriptions_period_idx'
            );
        });

        Schema::create('building_subscription_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('building_subscription_id')
                ->constrained('building_subscriptions')
                ->cascadeOnDelete();
            $table->foreignId('building_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('event_type', 40)->index();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['building_id', 'created_at'],
                'building_subscription_events_building_created_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('building_subscription_events');

        Schema::table('building_subscriptions', function (Blueprint $table): void {
            $table->dropIndex('building_subscriptions_period_idx');
            $table->dropIndex(['grace_ends_at']);
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn([
                'grace_ends_at',
                'cancelled_at',
            ]);
        });
    }
};
