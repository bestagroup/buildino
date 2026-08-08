<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SupportTicket\CreateSupportTicket;
use App\Actions\SupportTicket\UpdateSupportTicket;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupportTicketRequest;
use App\Http\Requests\UpdateSupportTicketRequest;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SupportTicket::class);

        $items = SupportTicket::query()
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 100));

        return response()->json($items);
    }

    public function store(StoreSupportTicketRequest $request, CreateSupportTicket $action): JsonResponse
    {
        $this->authorize('create', SupportTicket::class);
        $model = $action->execute($request->validated());

        return response()->json(['data' => $model], 201);
    }

    public function show(SupportTicket $model): JsonResponse
    {
        $this->authorize('view', $model);
        return response()->json(['data' => $model]);
    }

    public function update(UpdateSupportTicketRequest $request, SupportTicket $model, UpdateSupportTicket $action): JsonResponse
    {
        $this->authorize('update', $model);
        return response()->json(['data' => $action->execute($model, $request->validated())]);
    }

    public function destroy(SupportTicket $model): JsonResponse
    {
        $this->authorize('delete', $model);
        $model->delete();
        return response()->json(status: 204);
    }
}
