<?php

namespace App\Services\Web;

use App\Models\Building;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Support\Collection;

final class BuildingSetupProgressService
{
    public function __construct(private readonly PermissionChecker $permissions) {}

    public function incomplete(User $user, Collection $buildings): Collection
    {
        $ids = Building::query()->whereIn('id', $buildings->pluck('id')->all())
            ->where(function ($query): void {
                $query->where(fn ($q) => $q->whereNotNull('structure_type')->whereNull('setup_completed_at'))
                    ->orWhereDoesntHave('blocks.floors.units');
            })->pluck('id');

        return $buildings->filter(fn ($building) => $ids->contains($building->id)
            && $this->permissions->allows($user, 'blocks.create', $building)
            && $this->permissions->allows($user, 'floors.create', $building)
            && $this->permissions->allows($user, 'units.create', $building))->values();
    }
}
