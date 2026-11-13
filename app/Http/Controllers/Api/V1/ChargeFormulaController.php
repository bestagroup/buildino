<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChargeFormulaRequest;
use App\Http\Requests\UpdateChargeFormulaRequest;
use App\Http\Resources\V1\ChargeFormulaResource;
use App\Models\Building;
use App\Models\ChargeFormula;
use App\Models\FinancialCategory;
use App\Services\ChargeFormulaBuilder;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChargeFormulaController extends Controller
{
    public function index(
        Request $request,
        Building $building,
        PermissionChecker $permissions
    ): AnonymousResourceCollection {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'charge-formulas.view',
                $building
            ),
            403
        );

        return ChargeFormulaResource::collection(
            $building->chargeFormulas()
                ->with('chargeItems')
                ->latest('id')
                ->paginate(
                    min(max($request->integer('per_page',20),1),100)
                )
                ->withQueryString()
        );
    }

    public function store(
        StoreChargeFormulaRequest $request,
        Building $building,
        PermissionChecker $permissions,
        ChargeFormulaBuilder $builder
    ) {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'charge-formulas.create',
                $building
            ),
            403
        );

        $data = $builder->normalize($request->validated());
        $formula = DB::transaction(function () use ($building, $data): ChargeFormula {
            Building::query()->whereKey($building->getKey())->lockForUpdate()->firstOrFail();
            $this->validateCategories($building, $data['items']);
            if ($data['is_active'] ?? true) {
                $this->validateUniqueCategory($building, (int) $data['items'][0]['financial_category_id']);
            }

            $formula = $building->chargeFormulas()->create([
                'title'=>$data['title'],
                'calculation_type'=>$data['calculation_type'],
                'configuration'=>$data['configuration'] ?? null,
                'is_active'=>$data['is_active'] ?? true,
            ]);

            $formula->chargeItems()->createMany($data['items']);

            return $formula;
        }, 3);

        $formula->load('chargeItems');

        return (new ChargeFormulaResource($formula))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        ChargeFormula $chargeFormula
    ): ChargeFormulaResource {
        $this->authorize('view',$chargeFormula);
        $chargeFormula->load('chargeItems');

        return new ChargeFormulaResource($chargeFormula);
    }

    public function update(
        UpdateChargeFormulaRequest $request,
        ChargeFormula $chargeFormula,
        ChargeFormulaBuilder $builder
    ): ChargeFormulaResource {
        $this->authorize('update',$chargeFormula);

        $data = $builder->normalize($request->validated());

        $chargeFormula = DB::transaction(function () use ($chargeFormula, $data): ChargeFormula {
            $building = Building::query()->whereKey($chargeFormula->building_id)->lockForUpdate()->firstOrFail();
            $chargeFormula = ChargeFormula::query()->whereKey($chargeFormula->getKey())->lockForUpdate()->firstOrFail();

            if (isset($data['items'])) {
                $this->validateCategories($building, $data['items']);
            }

            // Validate the resulting state, including an activation-only PATCH.
            if ($data['is_active'] ?? $chargeFormula->is_active) {
                $categoryIds = isset($data['items'])
                    ? collect($data['items'])->pluck('financial_category_id')
                    : $chargeFormula->chargeItems()->pluck('financial_category_id');
                foreach ($categoryIds->filter()->unique() as $categoryId) {
                    app(\App\Services\Finance\ExpenseCategoryService::class)->requireUsable($building->id, (int) $categoryId);
                    $this->validateUniqueCategory($building, (int) $categoryId, $chargeFormula);
                }
            }

            $chargeFormula->update(
                array_intersect_key(
                    $data,
                    array_flip([
                        'title','calculation_type','configuration','is_active',
                    ])
                )
            );

            if (isset($data['items'])) {
                $chargeFormula->chargeItems()->delete();
                $chargeFormula->chargeItems()->createMany($data['items']);
            }

            return $chargeFormula;
        }, 3);

        $chargeFormula->load('chargeItems');

        return new ChargeFormulaResource($chargeFormula->refresh());
    }

    private function validateUniqueCategory(
        Building $building,
        int $financialCategoryId,
        ?ChargeFormula $ignore = null
    ): void {
        $query = ChargeFormula::query()
            ->where('is_active', true)
            ->where(
                'building_id',
                $building->getKey()
            )
            ->whereHas(
                'chargeItems',
                fn ($query) => $query->where(
                    'financial_category_id',
                    $financialCategoryId
                )
            );

        if ($ignore) {
            $query->whereKeyNot(
                $ignore->getKey()
            );
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'builder.financial_category_id' =>
                    'برای این دسته هزینه یک فرمول فعال وجود دارد. ابتدا فرمول قبلی را غیرفعال کنید.',
            ]);
        }
    }

    private function validateCategories(
        Building $building,
        array $items
    ): void {
        $ids = collect($items)
            ->pluck('financial_category_id')
            ->filter()
            ->map(fn($id)=>(int)$id)
            ->unique();

        if ($ids->isEmpty()) {
            return;
        }

        foreach ($ids as $id) {
            app(\App\Services\Finance\ExpenseCategoryService::class)->requireUsable($building->id, $id);
        }
    }
}
