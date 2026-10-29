<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Block;
use App\Models\Complex;
use App\Models\Floor;
use App\Services\System\IranLocations;
use App\Services\Web\ManagementDashboardAccessService;
use App\Services\Web\ManagementUiContextService;
use App\Support\ManagementPageCatalog;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ManagementOperationsController extends Controller
{
    public function index(
        ManagementUiContextService $ui
    ): View {
        $user = request()->user();

        $resources = collect(
            config('management_crud.resources', [])
        )->filter(
            fn (array $resource): bool => $ui->canSeeResource(
                $user,
                $resource
            )
        );

        $groups = collect(
            config('management_crud.groups', [])
        )->map(function (
            array $group,
            string $key
        ) use ($resources): array {
            $group['key'] = $key;
            $group['resources'] =
                $resources
                    ->filter(
                        fn (array $resource): bool => ($resource['group'] ?? null)
                            === $key
                    )
                    ->map(
                        fn (
                            array $resource,
                            string $resourceKey
                        ): array => [
                            ...$resource,
                            'key' => $resourceKey,
                        ]
                    )
                    ->values()
                    ->all();

            return $group;
        })->filter(
            fn (array $group): bool => count(
                $group['resources']
            ) > 0
        );

        return view(
            'management.operations.index',
            [
                'user' => request()->user(),
                'groups' => $groups,
                'resourceCount' => $resources->count(),
            ]
        );
    }

    public function show(
        string $resource,
        ManagementUiContextService $ui,
        PermissionChecker $permissions
    ): View|InertiaResponse {
        $configuration =
            config(
                "management_crud.resources.{$resource}"
            );

        abort_unless(
            is_array($configuration),
            Response::HTTP_NOT_FOUND
        );

        abort_unless(
            $ui->canSeeResource(
                request()->user(),
                $configuration
            ),
            Response::HTTP_FORBIDDEN
        );

        $configuration = $ui->resourceForUser(
            request()->user(),
            $configuration
        );

        if ($resource === 'ownerships') {
            return app(UnitOwnershipPageController::class)(request(), $permissions);
        }

        if ($resource === 'occupancies') {
            return app(UnitOccupancyPageController::class)(request(), $permissions);
        }

        if ($resource === 'guest-visits') {
            return app(GuestVisitPageController::class)(request(), $permissions);
        }

        if ($resource === 'facilities') {
            return app(FacilityConfigurationPageController::class)(request(), $permissions);
        }

        if ($resource === 'reservations') {
            return app(FacilityReservationPageController::class)(request(), $permissions);
        }

        if ($resource === 'charge-periods') {
            return app(ChargePeriodPageController::class)(request(), $permissions);
        }

        if (($configuration['group'] ?? null) === 'finance' && $resource !== 'charge-formulas') {
            return app(FinanceWorkspacePageController::class)(request(), $resource, $permissions, $ui);
        }

        $formulaBuildings = null;
        if ($resource === 'charge-formulas') {
            $user = request()->user();
            $formulaBuildings = Building::query()->orderBy('title')->get()
                ->filter(fn (Building $building): bool => $permissions->allows($user, 'charge-formulas.view', $building))
                ->map(fn (Building $building): array => [
                    'id' => $building->id,
                    'title' => $building->title,
                    'canCreate' => $permissions->allows($user, 'charge-formulas.create', $building),
                    'canUpdate' => $permissions->allows($user, 'charge-formulas.update', $building),
                    'canViewPeriods' => $permissions->allows($user, 'charge-periods.view', $building),
                ])->values();
        }

        $inertiaResources = ManagementPageCatalog::STRUCTURE_RESOURCES;

        if (
            in_array(
                $resource,
                $inertiaResources,
                true
            )
        ) {
            $groups = config(
                'management_crud.groups',
                []
            );
            $visibleResources = $ui->context(
                request()->user()
            )['visible_resources'] ?? [];

            $siblingResources = collect(
                config(
                    'management_crud.resources',
                    []
                )
            )
                ->filter(
                    fn (
                        array $item,
                        string $key
                    ): bool => ($item['group'] ?? null)
                            === ($configuration['group'] ?? null)
                        && in_array(
                            $key,
                            $visibleResources,
                            true
                        )
                )
                ->map(
                    fn (
                        array $item,
                        string $key
                    ): array => [
                        'key' => $key,
                        'title' => $item['title'],
                        'url' => route(
                            'management.operations.show',
                            $key
                        ),
                        'inertia' => in_array(
                            $key,
                            $inertiaResources,
                            true
                        ),
                    ]
                )
                ->values()
                ->all();

            return Inertia::render(
                'management/operations/Resource',
                [
                    'resourceKey' => $resource,
                    'resource' => $configuration,
                    'groupTitle' => data_get(
                        $groups,
                        ($configuration['group'] ?? '')
                            .'.title',
                        'Operations'
                    ),
                    'siblingResources' => $siblingResources,
                    'operationsUrl' => route(
                        'management.operations.index'
                    ),
                    'lookupBase' => url(
                        '/management/lookups'
                    ),
                    'csrfToken' => csrf_token(),
                    'structureUi' => $this->structureUi($resource, $permissions),
                ]
            );
        }

        return view(
            'management.operations.resource',
            [
                'user' => request()->user(),
                'resourceKey' => $resource,
                'resource' => $configuration,
                'formulaBuildings' => $formulaBuildings,
                'groups' => config(
                    'management_crud.groups',
                    []
                ),
            ]
        );
    }

    private function structureUi(string $resource, PermissionChecker $permissions): array
    {
        $user = request()->user();
        $accessibleBuildings = app(ManagementDashboardAccessService::class)->accessibleBuildings($user);
        $buildingIds = $accessibleBuildings->pluck('id')->all();
        $abilities = fn ($scope): array => collect(['create', 'update', 'delete'])
            ->mapWithKeys(fn (string $action): array => [$action => $permissions->allows($user, "$resource.$action", $scope)])->all();
        $childPermission = match ($resource) {
            'complexes' => 'buildings.view', 'buildings' => 'blocks.view',
            'blocks' => 'floors.view', 'floors' => 'units.view', default => null,
        };
        $childAccess = fn ($scope): bool => $childPermission !== null && $permissions->allows($user, $childPermission, $scope);
        $parents = match ($resource) {
            'blocks' => Building::query()->whereIn('id', $buildingIds)->with('complex')->orderBy('title')->get(),
            'floors' => Block::query()->whereIn('building_id', $buildingIds)->with('building.complex')->orderBy('sort_order')->get(),
            'units' => Floor::query()->whereHas('block', fn ($query) => $query->whereIn('building_id', $buildingIds))->with('block.building.complex')->orderBy('floor_number')->get(),
            default => collect(),
        };
        $parentBuilding = fn ($parent) => match ($resource) {
            'blocks' => $parent,
            'floors' => $parent->building,
            default => $parent->block->building,
        };
        $contexts = $parents->filter(fn ($parent): bool => $permissions->allows($user, "$resource.view", $parentBuilding($parent)))
            ->map(function ($parent) use ($resource, $abilities, $parentBuilding, $childAccess): array {
                $building = $parentBuilding($parent);
                $block = $resource === 'floors' ? $parent : ($resource === 'units' ? $parent->block : null);
                return [
                    'id' => $parent->id,
                    'label' => collect([$building->title, $block?->title, $resource === 'units' ? ($parent->title ?: 'طبقه '.$parent->floor_number) : null])->filter()->implode(' / '),
                    'building_id' => $building->id,
                    'block_id' => $block?->id,
                    'complex_id' => $building->complex_id,
                    'title' => $resource === 'units' ? ($parent->title ?: 'طبقه '.$parent->floor_number) : $parent->title,
                    ...$abilities($building),
                    'childView' => $childAccess($building),
                ];
            })->values();
        $roots = match ($resource) {
            'complexes' => Complex::query()->get(),
            'buildings' => Building::query()->whereIn('id', $buildingIds)->get(),
            default => collect(),
        };

        return [
            'contexts' => $contexts,
            'hierarchy' => [
                'buildings' => in_array($resource, ['blocks', 'floors', 'units'], true)
                    ? $accessibleBuildings->filter(fn (Building $building): bool => $permissions->allows($user, "$resource.view", $building))
                        ->map(fn (Building $building): array => ['id' => $building->id, 'title' => $building->title, 'complex_id' => $building->complex_id])->values()
                    : [],
                'blocks' => $resource === 'units'
                    ? Block::query()->whereIn('building_id', $buildingIds)->orderBy('sort_order')->orderBy('id')->get()
                        ->filter(fn (Block $block): bool => $permissions->allows($user, 'units.view', $accessibleBuildings->firstWhere('id', $block->building_id)))
                        ->map(fn (Block $block): array => ['id' => $block->id, 'title' => $block->title, 'building_id' => $block->building_id])->values()
                    : [],
            ],
            'abilities' => $roots->filter(fn ($model): bool => $permissions->allows($user, "$resource.view", $model))
                ->mapWithKeys(fn ($model): array => [$model->id => [...$abilities($model), 'childView' => $childAccess($model)]])->all(),
            'globalAbilities' => [...$abilities(null), 'childView' => $childAccess(null)],
            'parentAbilities' => $resource === 'buildings' ? Complex::query()->get()
                ->mapWithKeys(fn ($complex): array => [$complex->id => [...$abilities($complex), 'childView' => $childAccess($complex)]])->filter(fn (array $actions): bool => in_array(true, $actions, true))->all() : [],
            'canCreate' => $permissions->allowsAnyScope($user, "$resource.create"),
            'createParents' => $resource === 'buildings'
                ? Complex::query()->orderBy('title')->get()->filter(fn ($complex): bool => $permissions->allows($user, 'buildings.create', $complex))
                    ->map(fn ($complex): array => ['value' => $complex->id, 'label' => $complex->title])->values()
                : [],
            'canSetup' => $permissions->allowsAnyScope($user, 'buildings.create') || $permissions->allowsAnyScope($user, 'blocks.create'),
            'setupUrl' => route('management.building-setup'),
            'locations' => $resource === 'complexes' ? app(IranLocations::class)->forForm() : [],
        ];
    }
}
