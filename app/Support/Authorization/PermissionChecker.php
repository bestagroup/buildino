<?php

namespace App\Support\Authorization;

use App\Models\Building;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PermissionChecker
{
    public function allowsAnyScope(
        User $user,
        string $permission
    ): bool {
        if (! $user->is_active || $user->is_blocked) {
            return false;
        }

        return $this->activeAssignments($user)
            ->whereHas(
                'role.permissions',
                fn (Builder $query) => $query->where(
                    'permissions.name',
                    $permission
                )
            )
            ->exists();
    }

    public function allows(
        User $user,
        string $permission,
        ?Model $scope = null
    ): bool {
        if (! $user->is_active || $user->is_blocked) {
            return false;
        }

        return $this->activeAssignments($user)
            ->whereHas(
                'role.permissions',
                fn (Builder $query) => $query->where(
                    'permissions.name',
                    $permission
                )
            )
            ->where(
                fn (Builder $query) => $this->applyScope(
                    $query,
                    $scope
                )
            )
            ->exists();
    }

    private function activeAssignments(User $user): HasMany
    {
        return $user->userRoleAssignments()
            ->where('is_active', true)

            ->where(function (Builder $query): void {
                $query
                    ->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })

            ->where(function (Builder $query): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });
    }

    private function applyScope(
        Builder $query,
        ?Model $scope
    ): void {
        if ($scope === null) {
            $this->applyGlobalScope($query);

            return;
        }

        $query->where(function (Builder $query) use ($scope): void {

            /*
             * Global assignment.
             */
            $query->where(function (Builder $global): void {
                $this->applyGlobalScope($global);
            });

            /*
             * Exact resource scope.
             */
            $query->orWhere(function (Builder $scoped) use ($scope): void {
                $scoped
                    ->whereIn('scope_type', [
                        $scope->getMorphClass(),
                        $scope::class,
                    ])
                    ->where(
                        'scope_id',
                        $scope->getKey()
                    );
            });

            /*
             * Complex assignment also grants permission
             * to buildings belonging to that complex.
             */
            if (
                $scope instanceof Building
                && $scope->complex_id !== null
            ) {
                $scope->loadMissing('complex');

                if ($scope->complex !== null) {
                    $complex = $scope->complex;

                    $query->orWhere(
                        function (Builder $complexScope) use ($complex): void {
                            $complexScope
                                ->whereIn('scope_type', [
                                    $complex->getMorphClass(),
                                    $complex::class,
                                ])
                                ->where(
                                    'scope_id',
                                    $complex->getKey()
                                );
                        }
                    );
                }
            }
        });
    }

    private function applyGlobalScope(
        Builder $query
    ): void {
        $query
            ->whereNull('scope_type')
            ->whereNull('scope_id');
    }
}
