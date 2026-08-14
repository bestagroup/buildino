<?php

namespace App\Services\Web;

use App\Models\User;
use Illuminate\Support\Collection;

final class ManagementRoleDashboardService
{
    public function build(
        User $user,
        array $dashboard,
        array $uiContext
    ): array {
        $role =
            $this->resolveRole(
                collect(
                    $uiContext[
                        'roles'
                    ] ?? []
                )
            );

        $profile =
            config(
                "management_role_dashboard.profiles.{$role}"
            )
            ?? config(
                'management_role_dashboard.profiles.default',
                []
            );

        $visibleResources =
            collect(
                $uiContext[
                    'visible_resources'
                ] ?? []
            );

        $sections =
            $profile['sections']
            ?? [];

        if (
            $role === 'complex_manager'
            && ! data_get(
                $dashboard,
                'scope.selected_building_id'
            )
        ) {
            $sections['finance'] = false;
            $sections['receivables'] = false;
        }

        return [
            'role' =>
                $role,

            'profile' =>
                $profile,

            'kpis' =>
                $this->kpis(
                    $profile,
                    $dashboard
                ),

            'quick_actions' =>
                $this->quickActions(
                    $profile,
                    $visibleResources
                ),

            'modules' =>
                $this->modules(
                    $dashboard[
                        'modules'
                    ] ?? [],
                    $visibleResources
                ),

            'operation_keys' =>
                array_values(
                    $profile[
                        'operations'
                    ] ?? []
                ),

            'recent_keys' =>
                array_values(
                    $profile[
                        'recent'
                    ] ?? []
                ),

            'sections' =>
                $sections,

            'scope_label' =>
                $uiContext[
                    'access_label'
                ] ?? 'دسترسی فعال',
        ];
    }

    private function resolveRole(
        Collection $roles
    ): string {
        $names =
            $roles
                ->pluck('name')
                ->filter()
                ->values();

        foreach (
            config(
                'management_role_dashboard.priority',
                []
            )
            as $role
        ) {
            if (
                $names->contains(
                    $role
                )
            ) {
                return $role;
            }
        }

        return (string) (
            $names->first()
            ?: 'default'
        );
    }

    private function kpis(
        array $profile,
        array $dashboard
    ): array {
        $currency =
            (string) data_get(
                $dashboard,
                'scope.currency',
                'IRR'
            );

        return collect(
            $profile['kpis']
            ?? []
        )
            ->map(
                function (
                    array $item
                ) use (
                    $dashboard,
                    $currency
                ): array {
                    $value =
                        data_get(
                            $dashboard,
                            $item[
                                'source'
                            ] ?? '',
                            0
                        );

                    $unit =
                        (
                            $item['unit']
                            ?? ''
                        ) === 'currency'
                            ? $currency
                            : (
                                $item['unit']
                                ?? ''
                            );

                    return [
                        ...$item,

                        'value' =>
                            is_numeric(
                                $value
                            )
                                ? (int) $value
                                : $value,

                        'unit' =>
                            $unit,
                    ];
                }
            )
            ->values()
            ->all();
    }

    private function quickActions(
        array $profile,
        Collection $visibleResources
    ): array {
        return collect(
            $profile[
                'quick_actions'
            ] ?? []
        )
            ->filter(
                fn (
                    array $action
                ): bool =>
                    $visibleResources
                        ->contains(
                            $action[
                                'resource'
                            ] ?? null
                        )
            )
            ->values()
            ->all();
    }

    private function modules(
        array $modules,
        Collection $visibleResources
    ): array {
        $targets = [
            'buildings' => [
                'complexes',
                'buildings',
                'blocks',
                'floors',
                'units',
            ],
            'residents' => [
                'occupancies',
                'ownerships',
                'invitations',
            ],
            'guests' => [
                'guest-visits',
            ],
            'facilities' => [
                'facilities',
                'reservations',
            ],
            'finance' => [
                'invoices',
                'charge-periods',
                'expenses',
                'incomes',
            ],
            'wallets' => [
                'payments',
                'bank-accounts',
                'wallet-payouts',
                'bill-payments',
            ],
            'services' => [
                'service-requests',
            ],
            'support' => [
                'support-tickets',
            ],
            'notifications' => [
                'notification-preferences',
            ],
            'documents' => [
                'documents',
                'meeting-minutes',
            ],
            'reports' => [
                'report-exports',
            ],
            'security' => [
                'users',
                'roles',
                'role-assignments',
            ],
        ];

        return collect(
            $modules
        )
            ->map(
                function (
                    array $module
                ) use (
                    $targets,
                    $visibleResources
                ): ?array {
                    $target =
                        collect(
                            $targets[
                                $module['key']
                            ] ?? []
                        )
                            ->first(
                                fn (
                                    string $resource
                                ): bool =>
                                    $visibleResources
                                        ->contains(
                                            $resource
                                        )
                            );

                    if (! $target) {
                        return null;
                    }

                    return [
                        ...$module,
                        'target_resource' =>
                            $target,
                    ];
                }
            )
            ->filter()
            ->values()
            ->all();
    }
}
