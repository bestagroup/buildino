<?php

namespace App\Services\ServiceMarketplace;

use App\Models\Building;
use App\Models\BuildingServiceFinancialSetting;

final class BuildingServiceFinancialSettingService
{
    public function forBuilding(
        Building $building
    ): BuildingServiceFinancialSetting {
        return BuildingServiceFinancialSetting::query()
            ->firstOrCreate(
                [
                    'building_id' =>
                        $building->getKey(),
                ],
                [
                    'platform_commission_bps' => 1000,
                    'allow_user_wallet' => true,
                    'allow_unit_wallet' => true,
                    'is_active' => true,
                ]
            );
    }

    public function update(
        Building $building,
        array $data
    ): BuildingServiceFinancialSetting {
        $setting = $this->forBuilding(
            $building
        );

        $setting->update($data);

        return $setting->refresh();
    }
}
