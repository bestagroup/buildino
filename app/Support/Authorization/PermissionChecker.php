<?php

namespace App\Support\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PermissionChecker
{
    public function allows(User $user, string $permission, ?Model $scope = null): bool
    {
        if (! $user->is_active || $user->is_blocked) {
            return false;
        }

        return $user->userRoleAssignments()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->when(
                $scope,
                fn ($query) => $query->where(function ($scopeQuery) use ($scope): void {
                    $scopeQuery
                        ->where(function ($global): void {
                            $global->whereNull('scope_type')->whereNull('scope_id');
                        })
                        ->orWhere(function ($scoped) use ($scope): void {
                            $scoped->where('scope_type', $scope->getMorphClass())
                                ->where('scope_id', $scope->getKey());
                        });
                }),
                fn ($query) => $query->whereNull('scope_type')->whereNull('scope_id')
            )
            ->whereHas('role.permissions', fn ($query) => $query->where('name', $permission))
            ->exists();
    }
}
