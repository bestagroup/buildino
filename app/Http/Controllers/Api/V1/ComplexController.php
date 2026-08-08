<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Complex\CreateComplex;
use App\Actions\Complex\UpdateComplex;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComplexRequest;
use App\Http\Requests\UpdateComplexRequest;
use App\Models\Complex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplexController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Complex::class);

        $items = Complex::query()
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 100));

        return response()->json($items);
    }

    public function store(StoreComplexRequest $request, CreateComplex $action): JsonResponse
    {
        $this->authorize('create', Complex::class);
        $model = $action->execute($request->validated());

        return response()->json(['data' => $model], 201);
    }

    public function show(Complex $model): JsonResponse
    {
        $this->authorize('view', $model);
        return response()->json(['data' => $model]);
    }

    public function update(UpdateComplexRequest $request, Complex $model, UpdateComplex $action): JsonResponse
    {
        $this->authorize('update', $model);
        return response()->json(['data' => $action->execute($model, $request->validated())]);
    }

    public function destroy(Complex $model): JsonResponse
    {
        $this->authorize('delete', $model);
        $model->delete();
        return response()->json(status: 204);
    }
}
