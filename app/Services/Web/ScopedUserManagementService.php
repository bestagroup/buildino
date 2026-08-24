<?php

namespace App\Services\Web;

use App\Models\Block;
use App\Models\Building;
use App\Models\Complex;
use App\Models\ManagedUserScope;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class ScopedUserManagementService
{
    public function __construct(
        private readonly PermissionChecker $permissions
    ) {}

    public function applyVisibleUsers(
        Builder $query,
        User $actor,
        string $permission = 'users.view'
    ): Builder {
        if (
            $this->permissions->allows(
                $actor,
                $permission,
                null
            )
        ) {
            return $query;
        }

        $buckets = $this->scopeBuckets(
            $this->permissionScopes(
                $actor,
                $permission
            )
        );

        if (! $this->hasScopes($buckets)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(
            function (Builder $query) use (
                $actor,
                $buckets
            ): void {
                $query->whereKey($actor->getKey())
                    ->orWhereHas(
                        'managedUserScopes',
                        fn (Builder $scopes) => $this->applyScopeBuckets(
                            $scopes,
                            $buckets
                        )
                    )
                    ->orWhereHas(
                        'userRoleAssignments',
                        function (Builder $assignments) use ($buckets): void {
                            $assignments->active();
                            $this->applyScopeBuckets(
                                $assignments,
                                $buckets
                            );
                        }
                    )
                    ->orWhereHas(
                        'unitOwnershipsAsUser.unit.floor',
                        fn (Builder $floors) => $floors->whereIn(
                            'block_id',
                            $buckets['blocks']
                        )
                    )
                    ->orWhereHas(
                        'unitOccupanciesAsUser.unit.floor',
                        fn (Builder $floors) => $floors->whereIn(
                            'block_id',
                            $buckets['blocks']
                        )
                    );
            }
        );
    }

    public function canManage(
        User $actor,
        User $target,
        string $permission
    ): bool {
        return $this->applyVisibleUsers(
            User::query()->whereKey(
                $target->getKey()
            ),
            $actor,
            $permission
        )->exists();
    }

    public function attachCreatedUser(
        User $actor,
        User $target
    ): void {
        if (
            $this->permissions->allows(
                $actor,
                'users.create',
                null
            )
        ) {
            return;
        }

        $scopes = $this->permissionScopes(
            $actor,
            'users.create'
        );

        abort_if(
            $scopes->isEmpty(),
            403
        );

        $scopes->each(
            function (Model $scope) use (
                $actor,
                $target
            ): void {
                ManagedUserScope::query()
                    ->firstOrCreate(
                        [
                            'user_id' => $target->getKey(),
                            'scope_type' => $scope->getMorphClass(),
                            'scope_id' => $scope->getKey(),
                        ],
                        [
                            'assigned_by' => $actor->getKey(),
                        ]
                    );
            }
        );
    }

    /**
     * @return Collection<int, Model>
     */
    private function permissionScopes(
        User $actor,
        string $permission
    ): Collection {
        return $actor
            ->userRoleAssignments()
            ->active()
            ->whereNotNull('scope_type')
            ->whereNotNull('scope_id')
            ->whereHas(
                'role.permissions',
                fn (Builder $query) => $query->where(
                    'permissions.name',
                    $permission
                )
            )
            ->get([
                'scope_type',
                'scope_id',
            ])
            ->map(
                fn (UserRoleAssignment $assignment): ?Model => $this->resolveScope(
                    $assignment->scope_type,
                    (int) $assignment->scope_id
                )
            )
            ->filter()
            ->unique(
                fn (Model $scope): string => $scope->getMorphClass()
                    .':'
                    .$scope->getKey()
            )
            ->values();
    }

    private function resolveScope(
        string $type,
        int $id
    ): ?Model {
        foreach (
            [
                Complex::class,
                Building::class,
                Block::class,
            ] as $modelClass
        ) {
            $model = new $modelClass;

            if (
                in_array(
                    $type,
                    [
                        $modelClass,
                        $model->getMorphClass(),
                    ],
                    true
                )
            ) {
                return $modelClass::query()->find($id);
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Model>  $scopes
     * @return array{complexes: array<int, int>, buildings: array<int, int>, blocks: array<int, int>}
     */
    private function scopeBuckets(
        Collection $scopes
    ): array {
        $complexIds = $scopes
            ->filter(
                fn (Model $scope): bool => $scope instanceof Complex
            )
            ->modelKeys();

        $buildingIds = collect(
            $scopes
                ->filter(
                    fn (Model $scope): bool => $scope instanceof Building
                )
                ->modelKeys()
        )
            ->merge(
                Building::query()
                    ->whereIn('complex_id', $complexIds)
                    ->pluck('id')
            )
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $blockIds = collect(
            $scopes
                ->filter(
                    fn (Model $scope): bool => $scope instanceof Block
                )
                ->modelKeys()
        )
            ->merge(
                Block::query()
                    ->whereIn('building_id', $buildingIds)
                    ->pluck('id')
            )
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        return [
            'complexes' => array_map(
                'intval',
                $complexIds
            ),
            'buildings' => $buildingIds->all(),
            'blocks' => $blockIds->all(),
        ];
    }

    /**
     * @param  array{complexes: array<int, int>, buildings: array<int, int>, blocks: array<int, int>}  $buckets
     */
    private function applyScopeBuckets(
        Builder $query,
        array $buckets
    ): void {
        $query->where(
            function (Builder $query) use ($buckets): void {
                $first = true;

                foreach (
                    [
                        Complex::class => $buckets['complexes'],
                        Building::class => $buckets['buildings'],
                        Block::class => $buckets['blocks'],
                    ] as $modelClass => $ids
                ) {
                    if ($ids === []) {
                        continue;
                    }

                    $model = new $modelClass;
                    $method = $first ? 'where' : 'orWhere';

                    $query->{$method}(
                        function (Builder $scope) use (
                            $model,
                            $modelClass,
                            $ids
                        ): void {
                            $scope->whereIn(
                                'scope_type',
                                array_values(
                                    array_unique([
                                        $modelClass,
                                        $model->getMorphClass(),
                                    ])
                                )
                            )->whereIn(
                                'scope_id',
                                $ids
                            );
                        }
                    );

                    $first = false;
                }
            }
        );
    }

    /**
     * @param  array{complexes: array<int, int>, buildings: array<int, int>, blocks: array<int, int>}  $buckets
     */
    private function hasScopes(array $buckets): bool
    {
        return $buckets['complexes'] !== []
            || $buckets['buildings'] !== []
            || $buckets['blocks'] !== [];
    }
}
