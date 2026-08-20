<?php

namespace App\Services\Loyalty;

use App\Models\Building;
use App\Models\LoyaltyRule;
use Illuminate\Support\Facades\DB;

final class LoyaltyRuleService
{
    public function createVersion(
        Building $building,
        array $data
    ): LoyaltyRule {
        return DB::transaction(function () use ($building, $data): LoyaltyRule {
            $latest = LoyaltyRule::query()
                ->where('building_id', $building->getKey())
                ->where('event_type', $data['event_type'])
                ->lockForUpdate()
                ->orderByDesc('version')
                ->first();

            LoyaltyRule::query()
                ->where('building_id', $building->getKey())
                ->where('event_type', $data['event_type'])
                ->where('is_active', true)
                ->update(['is_active' => false]);

            return LoyaltyRule::query()->create([
                'building_id' => $building->getKey(),
                'event_type' => $data['event_type'],
                'version' => (int) ($latest?->version ?? 0) + 1,
                'points' => (int) $data['points'],
                'configuration' => $data['configuration'] ?? null,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
        }, 3);
    }
}
