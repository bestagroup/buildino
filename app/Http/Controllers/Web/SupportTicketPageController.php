<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\SupportCategory;
use App\Models\SupportTicket;
use App\Models\Unit;
use App\Services\Security\BuildingAccessService;
use App\Services\Security\BuildingPermissionScopeService;
use App\Services\Security\UnitResidentAccessService;
use App\Services\Support\SupportTicketAccessService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class SupportTicketPageController extends Controller
{
    public function __invoke(Request $request, PermissionChecker $permissions): Response
    {
        $user = $request->user();
        $visible = app(SupportTicketAccessService::class)->visibleQuery($user);
        $scopeIds = app(BuildingPermissionScopeService::class)->buildingIds($user, 'support-tickets.view');
        $abilities = function ($scope) use ($user, $permissions): array {
            return collect(['view', 'create', 'update', 'delete'])->mapWithKeys(fn ($key) => [$key => $permissions->allows($user, 'support-tickets.'.$key, $scope)])->all();
        };
        $buildings = Building::query()->when($scopeIds !== null, fn ($query) => $query->whereIn('id', collect($scopeIds)->merge((clone $visible)->whereNotNull('building_id')->distinct()->pluck('building_id'))->unique()))
            ->orderBy('title')->get()->map(fn (Building $building): array => [
                'id' => $building->id, 'title' => $building->title, 'abilities' => $abilities($building),
                'canCreate' => app(BuildingAccessService::class)->allows($user, $building),
            ])->values();
        $byId = $buildings->keyBy('id');
        $readableBuildings = $buildings->filter(fn (array $building) => $building['abilities']['view'])->pluck('id');
        $ticketUnits = (clone $visible)->whereNotNull('unit_id')->distinct()->pluck('unit_id');
        $units = Unit::query()->whereHas('floor.block', fn ($query) => $query->whereIn('building_id', $buildings->pluck('id')))
            ->where(fn ($query) => $query->whereHas('floor.block', fn ($block) => $block->whereIn('building_id', $readableBuildings))->orWhereIn('id', $ticketUnits))
            ->with('floor.block')->orderBy('unit_number')->get()->map(function (Unit $unit) use ($user, $byId): array {
                $buildingId = $unit->floor->block->building_id;
                $resident = app(UnitResidentAccessService::class)->allows($user, $unit);
                $scope = $byId->get($buildingId)['abilities'];

                return ['id' => $unit->id, 'building_id' => $buildingId,
                    'label' => $unit->floor->block->title.' / '.($unit->floor->title ?: 'طبقه '.$unit->floor->floor_number).' / واحد '.$unit->unit_number,
                    'canCreate' => $resident || $scope['create'], 'canUpdate' => $resident || $scope['update']];
            })->values();
        $categoryIds = (clone $visible)->whereNotNull('support_category_id')->distinct()->pluck('support_category_id');
        $categories = SupportCategory::query()->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $categoryIds))->orderBy('title')->get(['id', 'title', 'is_active']);

        return Inertia::render('management/support/Tickets', [
            'resourceKey' => 'support-tickets', 'resource' => ['title' => 'تیکت‌های پشتیبانی', 'description' => 'ثبت و پیگیری درخواست پشتیبانی'],
            'workspace' => ['buildings' => $buildings, 'units' => $units, 'categories' => $categories,
                'userId' => $user->id, 'canCreate' => Gate::forUser($user)->allows('create', SupportTicket::class),
                'globalAbilities' => $abilities(null), 'canManageCategories' => $permissions->allowsAnyScope($user, 'support-config.view'),
                'initial' => $request->only(['building_id', 'unit_id'])],
            'csrfToken' => csrf_token(),
        ]);
    }
}
