<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        /*
         * Preserve access for buildings created before subscriptions became
         * enforceable. New buildings are provisioned by the model observer.
         */
        $now = now();

        DB::table('plans')->updateOrInsert(
            ['code' => 'trial'],
            [
                'title' => 'آزمایشی',
                'description' => 'پلن آزمایشی پیش‌فرض Buildino',
                'price' => 0,
                'duration_days' => 14,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $trialPlanId = DB::table('plans')
            ->where('code', 'trial')
            ->value('id');

        if ($trialPlanId !== null) {
            DB::table('buildings')
                ->select('id')
                ->orderBy('id')
                ->each(function (object $building) use ($trialPlanId, $now): void {
                    $exists = DB::table('building_subscriptions')
                        ->where('building_id', $building->id)
                        ->exists();

                    if ($exists) {
                        return;
                    }

                    $expiresAt = $now->copy()->addDays(14);

                    DB::table('building_subscriptions')->insert([
                        'building_id' => $building->id,
                        'plan_id' => $trialPlanId,
                        'starts_at' => $now,
                        'expires_at' => $expiresAt,
                        'grace_ends_at' => $expiresAt->copy()->addDays(7),
                        'renewed_at' => null,
                        'suspended_at' => null,
                        'cancelled_at' => null,
                        'status' => 'active',
                        'limits' => null,
                        'metadata' => json_encode([
                            'source' => 'legacy_backfill',
                        ], JSON_THROW_ON_ERROR),
                        'created_by' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                });
        }
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
