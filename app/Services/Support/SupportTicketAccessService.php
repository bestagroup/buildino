<?php

namespace App\Services\Support;

use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Security\BuildingPermissionScopeService;
use Illuminate\Database\Eloquent\Builder;

final class SupportTicketAccessService
{
    public function __construct(
        private readonly BuildingPermissionScopeService $scopes
    ) {
    }

    public function visibleQuery(User $user): Builder
    {
        $buildingIds = $this->scopes->buildingIds(
            $user,
            'support-tickets.view'
        );

        $query = SupportTicket::query();

        if ($buildingIds === null) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user, $buildingIds): void {
            $query->where('user_id', $user->getKey());

            if ($buildingIds !== []) {
                $query->orWhereIn('building_id', $buildingIds);
            }
        });
    }
}
