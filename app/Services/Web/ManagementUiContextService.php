<?php

namespace App\Services\Web;

use App\Models\Building;
use App\Models\Complex;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Support\Collection;

final class ManagementUiContextService
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $cache = [];

    public function context(
        User $user
    ): array {
        if (
            isset(
                $this->cache[
                    $user->getKey()
                ]
            )
        ) {
            return $this->cache[
                $user->getKey()
            ];
        }

        $assignments =
            $user
                ->userRoleAssignments()
                ->active()
                ->with([
                    'role.permissions:id,name,module',
                ])
                ->get();

        $permissions =
            $assignments
                ->flatMap(
                    fn (
                        UserRoleAssignment $assignment
                    ): Collection =>
                        collect(
                            $assignment
                                ->role
                                ?->permissions
                            ?? []
                        )
                )
                ->pluck('name')
                ->filter()
                ->unique()
                ->values();

        $globalPermissions =
            $assignments
                ->filter(
                    fn (
                        UserRoleAssignment $assignment
                    ): bool =>
                        $assignment->scope_type
                            === null
                        && $assignment->scope_id
                            === null
                )
                ->flatMap(
                    fn (
                        UserRoleAssignment $assignment
                    ): Collection =>
                        collect(
                            $assignment
                                ->role
                                ?->permissions
                            ?? []
                        )
                )
                ->pluck('name')
                ->filter()
                ->unique()
                ->values();

        $roles =
            $assignments
                ->map(
                    function (
                        UserRoleAssignment $assignment
                    ): array {
                        return [
                            'name' =>
                                $assignment
                                    ->role
                                    ?->name,
                            'display_name' =>
                                $assignment
                                    ->role
                                    ?->display_name
                                ?: $assignment
                                    ->role
                                    ?->name
                                ?: 'نقش نامشخص',
                            'scope_type' =>
                                $this->scopeAlias(
                                    $assignment
                                        ->scope_type
                                ),
                            'scope_id' =>
                                $assignment
                                    ->scope_id,
                            'scope_label' =>
                                $this->scopeLabel(
                                    $assignment
                                ),
                            'is_system' =>
                                (bool) (
                                    $assignment
                                        ->role
                                        ?->is_system
                                    ?? false
                                ),
                        ];
                    }
                )
                ->values();

        $isSuperAdmin =
            $roles->contains(
                fn (array $role): bool =>
                    $role['name']
                        === 'superadmin'
                    && $role['scope_type']
                        === 'global'
            );

        $platformAccess =
            $globalPermissions
                ->contains(
                    'reports.platform.view'
                );

        $navigation =
            $this->navigation(
                $permissions,
                $globalPermissions,
                $platformAccess,
                $isSuperAdmin
            );

        $context = [
            'roles' =>
                $roles->all(),

            'primary_role' =>
                $roles->first(),

            'permissions' =>
                $permissions->all(),

            'global_permissions' =>
                $globalPermissions->all(),

            'is_superadmin' =>
                $isSuperAdmin,

            'platform_access' =>
                $platformAccess,

            'access_label' =>
                $platformAccess
                    || $isSuperAdmin
                    ? 'دسترسی سراسری'
                    : $this->scopeSummary(
                        $roles
                    ),

            'navigation' =>
                $navigation,

            'visible_resources' =>
                collect(
                    config(
                        'management_crud.resources',
                        []
                    )
                )
                    ->filter(
                        fn (
                            array $resource
                        ): bool =>
                            $this->resourceVisible(
                                $resource,
                                $permissions,
                                $globalPermissions
                            )
                    )
                    ->keys()
                    ->values()
                    ->all(),
        ];

        $this->cache[
            $user->getKey()
        ] = $context;

        return $context;
    }

    public function canSeeResource(
        User $user,
        array $resource
    ): bool {
        $permission =
            $resource['permission']
            ?? null;

        if (! $permission) {
            return true;
        }

        $context =
            $this->context(
                $user
            );

        return $this->resourceVisible(
            $resource,
            collect(
                $context[
                    'permissions'
                ]
            ),
            collect(
                $context[
                    'global_permissions'
                ]
            )
        );
    }

    /**
     * @return array<int, string>
     */
    public function visibleResourceKeys(
        User $user
    ): array {
        return $this->context(
            $user
        )['visible_resources'];
    }

    private function resourceVisible(
        array $resource,
        Collection $permissions,
        Collection $globalPermissions
    ): bool {
        $permission =
            $resource['permission']
            ?? null;

        if (! $permission) {
            return true;
        }

        $haystack =
            (
                $resource[
                    'permission_scope'
                ] ?? 'any'
            ) === 'global'
                ? $globalPermissions
                : $permissions;

        return $haystack->contains(
            $permission
        );
    }

    private function navigation(
        Collection $permissions,
        Collection $globalPermissions,
        bool $platformAccess,
        bool $isSuperAdmin
    ): array {
        $has = static function (
            Collection $collection,
            array $permissionNames
        ): bool {
            return collect(
                $permissionNames
            )
                ->contains(
                    fn (
                        string $permission
                    ): bool =>
                        $collection
                            ->contains(
                                $permission
                            )
                );
        };

        $globalUserAdmin =
            $isSuperAdmin
            || $has(
                $globalPermissions,
                [
                    'users.view',
                ]
            );

        return [
            'dashboard' => true,

            'operations' =>
                $permissions
                    ->isNotEmpty(),

            'structure' =>
                $isSuperAdmin
                || $has(
                    $permissions,
                    [
                        'complexes.view',
                        'buildings.view',
                        'blocks.view',
                        'floors.view',
                        'units.view',
                    ]
                ),

            'residents' =>
                $isSuperAdmin
                || $has(
                    $permissions,
                    [
                        'unit-ownerships.view',
                        'unit-occupancies.view',
                        'unit-invitations.view',
                    ]
                ),

            'guests' =>
                $isSuperAdmin
                || $has(
                    $permissions,
                    [
                        'guest-visits.view',
                        'guests.view',
                    ]
                ),

            'facilities' =>
                $isSuperAdmin
                || $has(
                    $permissions,
                    [
                        'facilities.view',
                        'facility-reservations.view',
                    ]
                ),

            'finance' =>
                $isSuperAdmin
                || $has(
                    $permissions,
                    [
                        'invoices.view',
                        'expenses.view',
                        'incomes.view',
                        'payments.view',
                        'wallets.view',
                        'building-wallet.view',
                        'wallet-payouts.view',
                        'building-bills.view',
                    ]
                ),

            'services' =>
                $isSuperAdmin
                || $has(
                    $permissions,
                    [
                        'service-requests.view',
                        'service-finance.manage',
                    ]
                ),

            'support' =>
                $isSuperAdmin
                || $has(
                    $permissions,
                    [
                        'support-tickets.view',
                        'support-config.view',
                    ]
                ),

            'content' =>
                $isSuperAdmin
                || $has(
                    $permissions,
                    [
                        'announcements.view',
                        'documents.view',
                        'meeting-minutes.view',
                    ]
                ),

            'reports' =>
                $isSuperAdmin
                || $platformAccess
                || $has(
                    $permissions,
                    [
                        'reports.view',
                        'reports.dashboard.view',
                        'reports.financial.view',
                        'reports.receivables.view',
                        'reports.operations.view',
                        'generated-reports.view',
                    ]
                ),

            'access' =>
                $globalUserAdmin,

            'system' =>
                $isSuperAdmin
                || $has(
                    $globalPermissions,
                    [
                        'system.health.view',
                        'system-settings.view',
                        'wallet-reconciliation.view',
                        'wallet-accounting.view',
                    ]
                ),
        ];
    }

    private function scopeSummary(
        Collection $roles
    ): string {
        $labels =
            $roles
                ->pluck(
                    'scope_label'
                )
                ->filter(
                    fn (
                        ?string $label
                    ): bool =>
                        $label !== null
                        && $label !== 'سراسری'
                )
                ->unique()
                ->values();

        if ($labels->isEmpty()) {
            return 'دسترسی محدود';
        }

        if ($labels->count() === 1) {
            return (string) $labels->first();
        }

        return number_format(
            $labels->count()
        )
            . ' محدوده دسترسی';
    }

    private function scopeLabel(
        UserRoleAssignment $assignment
    ): string {
        if (
            $assignment->scope_type
                === null
            && $assignment->scope_id
                === null
        ) {
            return 'سراسری';
        }

        if (
            $this->scopeAlias(
                $assignment
                    ->scope_type
            ) === 'building'
        ) {
            $building =
                Building::query()
                    ->select([
                        'id',
                        'title',
                    ])
                    ->find(
                        $assignment
                            ->scope_id
                    );

            return $building
                ? 'ساختمان '
                    . $building->title
                : 'ساختمان #'
                    . $assignment
                        ->scope_id;
        }

        if (
            $this->scopeAlias(
                $assignment
                    ->scope_type
            ) === 'complex'
        ) {
            $complex =
                Complex::query()
                    ->select([
                        'id',
                        'title',
                    ])
                    ->find(
                        $assignment
                            ->scope_id
                    );

            return $complex
                ? 'مجتمع '
                    . $complex->title
                : 'مجتمع #'
                    . $assignment
                        ->scope_id;
        }

        return 'محدوده #'
            . $assignment
                ->scope_id;
    }

    private function scopeAlias(
        ?string $scopeType
    ): string {
        if ($scopeType === null) {
            return 'global';
        }

        if (
            in_array(
                $scopeType,
                [
                    Building::class,
                    (new Building())
                        ->getMorphClass(),
                ],
                true
            )
        ) {
            return 'building';
        }

        if (
            in_array(
                $scopeType,
                [
                    Complex::class,
                    (new Complex())
                        ->getMorphClass(),
                ],
                true
            )
        ) {
            return 'complex';
        }

        return $scopeType;
    }
}
