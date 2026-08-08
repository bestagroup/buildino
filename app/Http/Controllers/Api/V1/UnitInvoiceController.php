<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UnitInvoice\CreateUnitInvoice;
use App\Actions\UnitInvoice\UpdateUnitInvoice;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnitInvoiceRequest;
use App\Http\Requests\UpdateUnitInvoiceRequest;
use App\Models\UnitInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitInvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', UnitInvoice::class);

        $items = UnitInvoice::query()
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 100));

        return response()->json($items);
    }

    public function store(StoreUnitInvoiceRequest $request, CreateUnitInvoice $action): JsonResponse
    {
        $this->authorize('create', UnitInvoice::class);
        $model = $action->execute($request->validated());

        return response()->json(['data' => $model], 201);
    }

    public function show(UnitInvoice $model): JsonResponse
    {
        $this->authorize('view', $model);
        return response()->json(['data' => $model]);
    }

    public function update(UpdateUnitInvoiceRequest $request, UnitInvoice $model, UpdateUnitInvoice $action): JsonResponse
    {
        $this->authorize('update', $model);
        return response()->json(['data' => $action->execute($model, $request->validated())]);
    }

    public function destroy(UnitInvoice $model): JsonResponse
    {
        $this->authorize('delete', $model);
        $model->delete();
        return response()->json(status: 204);
    }
}
