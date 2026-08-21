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
        $this->validateCategories($building, $data['items']);

        $formula = DB::transaction(function () use ($building,$data): ChargeFormula {
            $formula = $building->chargeFormulas()->create([
                'title'=>$data['title'],
                'calculation_type'=>$data['calculation_type'],
                'configuration'=>$data['configuration'] ?? null,
                'is_active'=>$data['is_active'] ?? true,
            ]);

            $formula->chargeItems()->createMany($data['items']);

            return $formula;
        });

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

        if (isset($data['items'])) {
            $this->validateCategories(
                $chargeFormula->building,
                $data['items']
            );
        }

        DB::transaction(function () use ($chargeFormula,$data): void {
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
        });

        $chargeFormula->load('chargeItems');

        return new ChargeFormulaResource($chargeFormula->refresh());
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

        $count = FinancialCategory::query()
            ->whereIn('id',$ids)
            ->where(function ($query) use ($building): void {
                $query
                    ->whereNull('building_id')
                    ->orWhere('building_id',$building->id);
            })
            ->count();

        if ($count !== $ids->count()) {
            throw ValidationException::withMessages([
                'items'=>'Charge item categories must be global or belong to the selected building.',
            ]);
        }
    }
}
