<?php

namespace App\Support\Authorization;

use App\Models\Complex;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ComplexVisibilityQuery
{
    public function apply(
        Builder $query,
        User $user
    ): Builder {
        if (
            ! $user->is_active
            || $user->is_blocked
        ) {
            return $query
                ->whereRaw(
                    '1 = 0'
                );
        }

        $assignments =
            $user
                ->userRoleAssignments()
                ->where(
                    'is_active',
                    true
                )
                ->whereHas(
                    'role.permissions',
                    fn (
                        Builder $query
                    ) =>
                        $query->where(
                            'permissions.name',
                            'complexes.view'
                        )
                )
                ->where(
                    function (
                        Builder $query
                    ): void {
                        $query
                            ->whereNull(
                                'starts_at'
                            )
                            ->orWhere(
                                'starts_at',
                                '<=',
                                now()
                            );
                    }
                )
                ->where(
                    function (
                        Builder $query
                    ): void {
                        $query
                            ->whereNull(
                                'ends_at'
                            )
                            ->orWhere(
                                'ends_at',
                                '>=',
                                now()
                            );
                    }
                );

        /*
        |--------------------------------------------------------------------------
        | Global assignment
        |--------------------------------------------------------------------------
        */

        if (
            (clone $assignments)
                ->whereNull(
                    'scope_type'
                )
                ->whereNull(
                    'scope_id'
                )
                ->exists()
        ) {
            return $query;
        }

        /*
        |--------------------------------------------------------------------------
        | Exact Complex scope
        |--------------------------------------------------------------------------
        */

        $scopeTypes =
            array_values(
                array_unique([
                    (new Complex())
                        ->getMorphClass(),

                    Complex::class,
                ])
            );

        $complexIds =
            (clone $assignments)
                ->whereIn(
                    'scope_type',
                    $scopeTypes
                )
                ->whereNotNull(
                    'scope_id'
                )
                ->pluck(
                    'scope_id'
                )
                ->map(
                    fn ($id): int =>
                        (int) $id
                )
                ->unique()
                ->values();

        if ($complexIds->isEmpty()) {
            return $query
                ->whereRaw(
                    '1 = 0'
                );
        }

        return $query
            ->whereIn(
                'id',
                $complexIds->all()
            );
    }
}
