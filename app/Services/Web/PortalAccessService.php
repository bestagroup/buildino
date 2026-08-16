<?php

namespace App\Services\Web;

use App\Models\ServiceRequest;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PortalAccessService
{
    public function hasAnyAccess(User $user): bool
    {
        return $this->hasResidentAccess($user)
            || $this->hasProviderAccess($user);
    }

    public function hasResidentAccess(User $user): bool
    {
        return $this->residentUnits($user)
            ->isNotEmpty();
    }

    public function hasProviderAccess(User $user): bool
    {
        if (
            ! $user->is_active
            || $user->is_blocked
        ) {
            return false;
        }

        if (
            $user->hasRole(
                'service_provider'
            )
        ) {
            return true;
        }

        return ServiceRequest::query()
            ->where(
                'assigned_to',
                $user->getKey()
            )
            ->exists();
    }

    public function defaultArea(User $user): string
    {
        return $this->hasResidentAccess($user)
            ? 'resident'
            : 'provider';
    }

    /**
     * @return Collection<int, Unit>
     */
    public function residentUnits(User $user): Collection
    {
        if (
            ! $user->is_active
            || $user->is_blocked
        ) {
            return collect();
        }

        $today = now()
            ->toDateString();

        return Unit::query()
            ->with([
                'floor.block.building.complex',
                'wallets',
            ])
            ->where(
                function (
                    Builder $query
                ) use (
                    $user,
                    $today
                ): void {
                    $query
                        ->whereHas(
                            'unitOwnerships',
                            fn (
                                Builder $relation
                            ) =>
                                $this
                                    ->activeResidentRelation(
                                        $relation,
                                        $user,
                                        $today
                                    )
                        )
                        ->orWhereHas(
                            'unitOccupancies',
                            fn (
                                Builder $relation
                            ) =>
                                $this
                                    ->activeResidentRelation(
                                        $relation,
                                        $user,
                                        $today
                                    )
                        );
                }
            )
            ->orderBy('id')
            ->get();
    }

    public function residentRelationship(
        User $user,
        Unit $unit
    ): array {
        $today = now()
            ->toDateString();

        $ownership =
            UnitOwnership::query()
                ->where(
                    'unit_id',
                    $unit->getKey()
                )
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->where(
                    'is_active',
                    true
                )
                ->whereDate(
                    'starts_at',
                    '<=',
                    $today
                )
                ->where(
                    function (
                        Builder $query
                    ) use ($today): void {
                        $query
                            ->whereNull(
                                'ends_at'
                            )
                            ->orWhereDate(
                                'ends_at',
                                '>=',
                                $today
                            );
                    }
                )
                ->first();

        if ($ownership) {
            return [
                'type' => 'owner',
                'label' => 'مالک',
                'is_primary' =>
                    (bool) $ownership
                        ->is_primary,
                'ownership_percentage' =>
                    (float) $ownership
                        ->ownership_percentage,
            ];
        }

        $occupancy =
            UnitOccupancy::query()
                ->where(
                    'unit_id',
                    $unit->getKey()
                )
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->where(
                    'is_active',
                    true
                )
                ->whereDate(
                    'starts_at',
                    '<=',
                    $today
                )
                ->where(
                    function (
                        Builder $query
                    ) use ($today): void {
                        $query
                            ->whereNull(
                                'ends_at'
                            )
                            ->orWhereDate(
                                'ends_at',
                                '>=',
                                $today
                            );
                    }
                )
                ->first();

        return [
            'type' => 'tenant',
            'label' =>
                $occupancy
                    ? 'مستأجر / ساکن'
                    : 'ساکن',
            'is_primary' =>
                (bool) (
                    $occupancy
                        ?->is_primary
                    ?? false
                ),
            'ownership_percentage' =>
                null,
        ];
    }

    private function activeResidentRelation(
        Builder $query,
        User $user,
        string $today
    ): void {
        $query
            ->where(
                'user_id',
                $user->getKey()
            )
            ->where(
                'is_active',
                true
            )
            ->whereDate(
                'starts_at',
                '<=',
                $today
            )
            ->where(
                function (
                    Builder $query
                ) use ($today): void {
                    $query
                        ->whereNull(
                            'ends_at'
                        )
                        ->orWhereDate(
                            'ends_at',
                            '>=',
                            $today
                        );
                }
            );
    }
}
