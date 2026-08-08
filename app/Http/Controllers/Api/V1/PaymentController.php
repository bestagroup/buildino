<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payment\CreatePayment;
use App\Actions\Payment\UpdatePayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $items = Payment::query()
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 100));

        return response()->json($items);
    }

    public function store(StorePaymentRequest $request, CreatePayment $action): JsonResponse
    {
        $this->authorize('create', Payment::class);
        $model = $action->execute($request->validated());

        return response()->json(['data' => $model], 201);
    }

    public function show(Payment $model): JsonResponse
    {
        $this->authorize('view', $model);
        return response()->json(['data' => $model]);
    }

    public function update(UpdatePaymentRequest $request, Payment $model, UpdatePayment $action): JsonResponse
    {
        $this->authorize('update', $model);
        return response()->json(['data' => $action->execute($model, $request->validated())]);
    }

    public function destroy(Payment $model): JsonResponse
    {
        $this->authorize('delete', $model);
        $model->delete();
        return response()->json(status: 204);
    }
}
