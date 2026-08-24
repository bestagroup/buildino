<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Building;
use App\Models\BuildingBankAccount;
use App\Models\BuildingFacility;
use App\Models\Complex;
use App\Models\FacilitySchedule;
use App\Models\FinancialCategory;
use App\Models\Floor;
use App\Models\Fund;
use App\Models\Permission;
use App\Models\ProviderBankAccount;
use App\Models\ReportDefinition;
use App\Models\Role;
use App\Models\SupportCategory;
use App\Models\Unit;
use App\Models\UnitInvoice;
use App\Models\User;
use App\Services\Web\ManagementDashboardAccessService;
use App\Services\Web\ScopedUserManagementService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ManagementLookupController extends Controller
{
    public function __construct(
        private readonly ManagementDashboardAccessService $access,
        private readonly PermissionChecker $permissions,
        private readonly ScopedUserManagementService $scopedUsers
    ) {
    }

    public function __invoke(
        Request $request,
        string $type
    ): JsonResponse {
        $user = $request->user();

        $buildings =
            $this->access
                ->accessibleBuildings(
                    $user
                );

        $buildingIds =
            $buildings
                ->pluck('id')
                ->map(
                    fn ($id): int =>
                        (int) $id
                )
                ->values();

        $platform =
            $this->access
                ->hasPlatformAccess(
                    $user
                );

        $items = match ($type) {
            'complexes' =>
                Complex::query()
                    ->when(
                        ! $platform,
                        fn (Builder $query) =>
                            $query->whereHas(
                                'buildings',
                                fn (Builder $builder) =>
                                    $builder->whereIn(
                                        'id',
                                        $buildingIds->all()
                                    )
                            )
                    )
                    ->orderBy('title')
                    ->limit(500)
                    ->get([
                        'id',
                        'code',
                        'title',
                    ])
                    ->map(
                        fn (Complex $item): array => [
                            'id' => $item->id,
                            'label' =>
                                "{$item->title} ({$item->code})",
                        ]
                    ),

            'buildings' =>
                $buildings
                    ->map(
                        fn (Building $item): array => [
                            'id' => $item->id,
                            'label' =>
                                "{$item->title} ({$item->code})",
                        ]
                    ),

            'blocks' =>
                Block::query()
                    ->whereIn(
                        'building_id',
                        $buildingIds->all()
                    )
                    ->when(
                        $request->filled('building_id'),
                        fn (Builder $query) =>
                            $query->where(
                                'building_id',
                                $request->integer(
                                    'building_id'
                                )
                            )
                    )
                    ->orderBy('building_id')
                    ->orderBy('sort_order')
                    ->get([
                        'id',
                        'building_id',
                        'title',
                    ])
                    ->map(
                        fn (Block $item): array => [
                            'id' => $item->id,
                            'label' => $item->title,
                            'building_id' =>
                                $item->building_id,
                        ]
                    ),

            'floors' =>
                Floor::query()
                    ->whereHas(
                        'block',
                        fn (Builder $query) =>
                            $query->whereIn(
                                'building_id',
                                $buildingIds->all()
                            )
                    )
                    ->when(
                        $request->filled('block_id'),
                        fn (Builder $query) =>
                            $query->where(
                                'block_id',
                                $request->integer(
                                    'block_id'
                                )
                            )
                    )
                    ->orderBy('block_id')
                    ->orderBy('floor_number')
                    ->get([
                        'id',
                        'block_id',
                        'floor_number',
                        'title',
                    ])
                    ->map(
                        fn (Floor $item): array => [
                            'id' => $item->id,
                            'label' =>
                                $item->title
                                ?: "طبقه {$item->floor_number}",
                            'block_id' =>
                                $item->block_id,
                        ]
                    ),

            'units' =>
                Unit::query()
                    ->whereHas(
                        'floor.block',
                        fn (Builder $query) =>
                            $query->whereIn(
                                'building_id',
                                $buildingIds->all()
                            )
                    )
                    ->when(
                        $request->filled('floor_id'),
                        fn (Builder $query) =>
                            $query->where(
                                'floor_id',
                                $request->integer(
                                    'floor_id'
                                )
                            )
                    )
                    ->orderBy('floor_id')
                    ->orderBy('unit_number')
                    ->limit(1500)
                    ->get([
                        'id',
                        'floor_id',
                        'unit_number',
                        'title',
                    ])
                    ->map(
                        fn (Unit $item): array => [
                            'id' => $item->id,
                            'label' =>
                                $item->title
                                ? "{$item->unit_number} — {$item->title}"
                                : "واحد {$item->unit_number}",
                            'floor_id' =>
                                $item->floor_id,
                        ]
                    ),

            'users' =>
                $this->userLookup(
                    $request,
                    $buildingIds,
                    $platform
                ),

            'roles' =>
                $this->globalUserAdminLookup(
                    $request,
                    Role::query()
                        ->orderBy('display_name')
                        ->get([
                            'id',
                            'name',
                            'display_name',
                        ])
                        ->map(
                            fn (Role $item): array => [
                                'id' => $item->id,
                                'label' =>
                                    $item->display_name
                                    ?: $item->name,
                            ]
                        )
                ),

            'permissions' =>
                $this->globalUserAdminLookup(
                    $request,
                    Permission::query()
                        ->orderBy('module')
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
                            'module',
                        ])
                        ->map(
                            fn (Permission $item): array => [
                                'id' => $item->id,
                                'label' =>
                                    "{$item->module} — {$item->name}",
                            ]
                        )
                ),

            'facilities' =>
                BuildingFacility::query()
                    ->whereIn(
                        'building_id',
                        $buildingIds->all()
                    )
                    ->when(
                        $request->filled('building_id'),
                        fn (Builder $query) =>
                            $query->where(
                                'building_id',
                                $request->integer(
                                    'building_id'
                                )
                            )
                    )
                    ->orderBy('title')
                    ->get([
                        'id',
                        'building_id',
                        'code',
                        'title',
                    ])
                    ->map(
                        fn (BuildingFacility $item): array => [
                            'id' => $item->id,
                            'label' =>
                                "{$item->title} ({$item->code})",
                            'building_id' =>
                                $item->building_id,
                        ]
                    ),

            'facility_schedules' =>
                FacilitySchedule::query()
                    ->whereHas(
                        'buildingFacility',
                        fn (Builder $query) =>
                            $query->whereIn(
                                'building_id',
                                $buildingIds->all()
                            )
                    )
                    ->when(
                        $request->filled('building_facility_id'),
                        fn (Builder $query) =>
                            $query->where(
                                'building_facility_id',
                                $request->integer(
                                    'building_facility_id'
                                )
                            )
                    )
                    ->orderBy('day_of_week')
                    ->orderBy('start_time')
                    ->get([
                        'id',
                        'building_facility_id',
                        'day_of_week',
                        'start_time',
                        'end_time',
                    ])
                    ->map(
                        fn (FacilitySchedule $item): array => [
                            'id' => $item->id,
                            'label' =>
                                "روز {$item->day_of_week} | {$item->start_time}-{$item->end_time}",
                            'building_facility_id' =>
                                $item->building_facility_id,
                        ]
                    ),

            'financial_categories' =>
                FinancialCategory::query()
                    ->whereIn(
                        'building_id',
                        $buildingIds->all()
                    )
                    ->when(
                        $request->filled('building_id'),
                        fn (Builder $query) =>
                            $query->where(
                                'building_id',
                                $request->integer(
                                    'building_id'
                                )
                            )
                    )
                    ->orderBy('title')
                    ->get([
                        'id',
                        'building_id',
                        'title',
                        'type',
                    ])
                    ->map(
                        fn (FinancialCategory $item): array => [
                            'id' => $item->id,
                            'label' =>
                                "{$item->title} ({$item->type->value})",
                            'building_id' =>
                                $item->building_id,
                        ]
                    ),

            'funds' =>
                Fund::query()
                    ->whereIn(
                        'building_id',
                        $buildingIds->all()
                    )
                    ->when(
                        $request->filled('building_id'),
                        fn (Builder $query) =>
                            $query->where(
                                'building_id',
                                $request->integer(
                                    'building_id'
                                )
                            )
                    )
                    ->orderBy('title')
                    ->get([
                        'id',
                        'building_id',
                        'title',
                    ])
                    ->map(
                        fn (Fund $item): array => [
                            'id' => $item->id,
                            'label' => $item->title,
                            'building_id' =>
                                $item->building_id,
                        ]
                    ),

            'support_categories' =>
                SupportCategory::query()
                    ->orderByDesc('is_active')
                    ->orderBy('title')
                    ->get([
                        'id',
                        'title',
                    ])
                    ->map(
                        fn (SupportCategory $item): array => [
                            'id' => $item->id,
                            'label' => $item->title,
                        ]
                    ),

            'invoices' =>
                UnitInvoice::query()
                    ->whereIn(
                        'building_id',
                        $buildingIds->all()
                    )
                    ->when(
                        $request->filled('building_id'),
                        fn (Builder $query) =>
                            $query->where(
                                'building_id',
                                $request->integer(
                                    'building_id'
                                )
                            )
                    )
                    ->latest('id')
                    ->limit(500)
                    ->get([
                        'id',
                        'building_id',
                        'invoice_number',
                        'outstanding_amount',
                    ])
                    ->map(
                        fn (UnitInvoice $item): array => [
                            'id' => $item->id,
                            'label' =>
                                "{$item->invoice_number} — {$item->outstanding_amount}",
                            'building_id' =>
                                $item->building_id,
                        ]
                    ),

            'building_bank_accounts' =>
                BuildingBankAccount::query()
                    ->whereIn(
                        'building_id',
                        $buildingIds->all()
                    )
                    ->when(
                        $request->filled('building_id'),
                        fn (Builder $query) =>
                            $query->where(
                                'building_id',
                                $request->integer(
                                    'building_id'
                                )
                            )
                    )
                    ->latest('is_default')
                    ->get([
                        'id',
                        'building_id',
                        'bank_name',
                        'account_holder_name',
                        'iban',
                    ])
                    ->map(
                        fn (BuildingBankAccount $item): array => [
                            'id' => $item->id,
                            'label' =>
                                trim(
                                    "{$item->bank_name} — {$item->account_holder_name} — {$item->iban}"
                                ),
                            'building_id' =>
                                $item->building_id,
                        ]
                    ),

            'provider_bank_accounts' =>
                ProviderBankAccount::query()
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->get([
                        'id',
                        'bank_name',
                        'account_holder_name',
                        'iban',
                    ])
                    ->map(
                        fn (ProviderBankAccount $item): array => [
                            'id' => $item->id,
                            'label' =>
                                trim(
                                    "{$item->bank_name} — {$item->account_holder_name} — {$item->iban}"
                                ),
                        ]
                    ),

            'report_definitions' =>
                ReportDefinition::query()
                    ->where(
                        'is_active',
                        true
                    )
                    ->orderBy('title')
                    ->get([
                        'id',
                        'key',
                        'title',
                    ])
                    ->map(
                        fn (ReportDefinition $item): array => [
                            'id' => $item->id,
                            'label' =>
                                "{$item->title} ({$item->key})",
                        ]
                    ),

            default =>
                abort(
                    Response::HTTP_NOT_FOUND
                ),
        };

        return response()->json([
            'data' => $items->values(),
        ]);
    }

    private function userLookup(
        Request $request,
        $buildingIds,
        bool $platform
    ) {
        $query = User::query()
            ->where(
                'is_active',
                true
            )
            ->where(
                'is_blocked',
                false
            );

        if (! $platform) {
            if (
                $this->permissions->allowsAnyScope(
                    $request->user(),
                    'users.view'
                )
            ) {
                $this->scopedUsers->applyVisibleUsers(
                    $query,
                    $request->user(),
                    'users.view'
                );
            } else {
                $query->where(function (Builder $query) use ($buildingIds): void {
                    $query
                        ->whereHas(
                            'unitOwnershipsAsUser.unit.floor.block',
                            fn (Builder $builder) =>
                                $builder->whereIn(
                                    'building_id',
                                    $buildingIds->all()
                                )
                        )
                        ->orWhereHas(
                            'unitOccupanciesAsUser.unit.floor.block',
                            fn (Builder $builder) =>
                                $builder->whereIn(
                                    'building_id',
                                    $buildingIds->all()
                                )
                        );
                });
            }
        }

        if (
            $search = trim(
                (string) $request->query('search')
            )
        ) {
            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->where(
                        'first_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'last_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'mobile',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        return $query
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(800)
            ->get([
                'id',
                'first_name',
                'last_name',
                'mobile',
            ])
            ->map(
                fn (User $item): array => [
                    'id' => $item->id,
                    'label' =>
                        trim(
                            "{$item->first_name} {$item->last_name}"
                        )
                        . " — {$item->mobile}",
                ]
            );
    }

    private function globalUserAdminLookup(
        Request $request,
        $items
    ) {
        abort_unless(
            $this->permissions->allows(
                $request->user(),
                'users.view',
                null
            ),
            403
        );

        return $items;
    }
}
