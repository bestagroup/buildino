<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\BuildingServiceFinancialSetting;
use App\Models\ServiceRequest;
use App\Models\Unit;
use App\Services\Security\BuildingAccessService;
use App\Services\Security\BuildingPermissionScopeService;
use App\Services\Security\UnitResidentAccessService;
use App\Services\ServiceMarketplace\ServiceRequestAccessService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ServiceRequestPageController extends Controller
{
    public function __invoke(Request $request, PermissionChecker $permissions): Response
    {
        $user = $request->user();
        $ids = app(BuildingPermissionScopeService::class)->buildingIds($user, 'service-requests.view');
        $relatedIds = app(ServiceRequestAccessService::class)->visibleQuery($user)->distinct()->pluck('building_id');
        $relatedUnitIds = app(ServiceRequestAccessService::class)->visibleQuery($user)->whereNotNull('unit_id')->distinct()->pluck('unit_id');
        $buildingModels = Building::query()->when($ids !== null, fn ($query) => $query->whereIn('id', collect($ids)->merge($relatedIds)->unique()))
            ->orderBy('title')->get();
        $settings = BuildingServiceFinancialSetting::query()->whereIn('building_id', $buildingModels->pluck('id'))->get()->keyBy('building_id');
        $buildings = $buildingModels->map(function (Building $building) use ($user, $permissions, $settings): array {
                $abilities = [];
                foreach (['service-requests.view', 'service-requests.create', 'service-requests.update', 'service-requests.delete', 'service-finance.quote', 'service-finance.manage', 'service-finance.settle'] as $key) {
                    $abilities[$key] = $permissions->allows($user, $key, $building);
                }
                $setting = $settings->get($building->id);

                return ['id' => $building->id, 'title' => $building->title, 'currency' => $building->currency ?: 'IRR', 'abilities' => $abilities,
                    'canCreate' => Gate::forUser($user)->allows('create', ServiceRequest::class) && app(BuildingAccessService::class)->allows($user, $building),
                    'finance' => ['active' => $setting?->is_active ?? true, 'user_wallet' => $setting?->allow_user_wallet ?? true, 'unit_wallet' => $setting?->allow_unit_wallet ?? true]];
            })->values();
        $byId = $buildings->keyBy('id');
        $readableUnitBuildings = $buildings->filter(fn (array $building) => $building['abilities']['service-requests.view'])->pluck('id');
        $units = Unit::query()->whereHas('floor.block', fn ($query) => $query->whereIn('building_id', $buildings->pluck('id')))
            ->where(fn ($query) => $query->whereHas('floor.block', fn ($block) => $block->whereIn('building_id', $readableUnitBuildings))->orWhereIn('id', $relatedUnitIds))
            ->with('floor.block')->orderBy('unit_number')->get()->map(function (Unit $unit) use ($user, $byId): array {
                $buildingId = $unit->floor->block->building_id;
                $resident = app(UnitResidentAccessService::class)->allows($user, $unit);
                $abilities = $byId->get($buildingId)['abilities'];

                return ['id' => $unit->id, 'building_id' => $buildingId,
                    'label' => $unit->floor->block->title.' / '.($unit->floor->title ?: 'طبقه '.$unit->floor->floor_number).' / واحد '.$unit->unit_number,
                    'canCreate' => $resident || $abilities['service-requests.create'], 'canUpdate' => $resident || $abilities['service-requests.update'], 'canUseWallet' => $resident];
            })->values();

        return Inertia::render('management/services/Requests', [
            'resourceKey' => 'service-requests', 'resource' => ['title' => 'درخواست خدمات', 'description' => 'ثبت و پیگیری خدمات ساختمان'],
            'workspace' => ['buildings' => $buildings, 'units' => $units, 'userId' => $user->id, 'initial' => $request->only(['building_id', 'unit_id'])],
            'csrfToken' => csrf_token(),
        ]);
    }
}
