<?php

namespace App\Services\ServiceMarketplace;

use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Security\BuildingPermissionScopeService;
use Illuminate\Database\Eloquent\Builder;

final class ServiceRequestAccessService
{
    public function __construct(
        private readonly BuildingPermissionScopeService $scopes
    ) {
    }

    public function visibleQuery(User $user): Builder
    {
        $buildingIds = $this->scopes->buildingIds(
            $user,
            'service-requests.view'
        );

        $query = ServiceRequest::query();

        if ($buildingIds === null) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user, $buildingIds): void {
            $query
                ->where('requested_by', $user->getKey())
                ->orWhere('assigned_to', $user->getKey());

            if ($buildingIds !== []) {
                $query->orWhereIn('building_id', $buildingIds);
            }
        });
    }
}
