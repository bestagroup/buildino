<?php

namespace App\Actions\Building;

use App\Models\Building;
use App\Models\User;
use App\Services\Subscription\TrialSubscriptionProvisioner;
use Illuminate\Support\Facades\DB;

class CreateBuilding
{
    public function __construct(
        private readonly TrialSubscriptionProvisioner $trials
    ) {
    }

    public function execute(
        array $data,
        ?User $actor = null
    ): Building {
        return DB::transaction(function () use ($data, $actor): Building {
            $building = Building::query()->create($data);

            $this->trials->provision(
                $building,
                $actor
            );

            return $building;
        });
    }
}
