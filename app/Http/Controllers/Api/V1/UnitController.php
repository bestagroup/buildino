<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Unit\CreateUnit;
use App\Actions\Unit\UpdateUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Unit::class);

        $items = Unit::query()
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 100));

        return response()->json($items);
    }

    public function store(StoreUnitRequest $request, CreateUnit $action): JsonResponse
    {
        $this->authorize('create', Unit::class);
        $model = $action->execute($request->validated());

        return response()->json(['data' => $model], 201);
    }

    public function show(Unit $model): JsonResponse
    {
        $this->authorize('view', $model);
        return response()->json(['data' => $model]);
    }

    public function update(UpdateUnitRequest $request, Unit $model, UpdateUnit $action): JsonResponse
    {
        $this->authorize('update', $model);
        return response()->json(['data' => $action->execute($model, $request->validated())]);
    }

    public function destroy(Unit $model): JsonResponse
    {
        $this->authorize('delete', $model);
        $model->delete();
        return response()->json(status: 204);
    }
}
