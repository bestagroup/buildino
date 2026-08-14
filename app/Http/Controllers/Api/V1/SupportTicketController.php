<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupportTicketRequest;
use App\Http\Requests\UpdateSupportTicketRequest;
use App\Models\SupportTicket;
use App\Services\Support\SupportTicketAccessService;
use App\Services\Support\SupportTicketWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function index(
        Request $request,
        SupportTicketAccessService $access
    ): JsonResponse {
        $this->authorize('viewAny', SupportTicket::class);

        $items = $access
            ->visibleQuery($request->user())
            ->with([
                'supportCategory:id,title',
                'assignedTo:id,first_name,last_name,mobile',
            ])
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->string('status')->toString()
                )
            )
            ->when(
                $request->filled('priority'),
                fn ($query) => $query->where(
                    'priority',
                    $request->string('priority')->toString()
                )
            )
            ->latest('id')
            ->paginate(
                min(
                    max((int) $request->integer('per_page', 20), 1),
                    100
                )
            );

        return response()->json($items);
    }

    public function store(
        StoreSupportTicketRequest $request,
        SupportTicketWorkflowService $service
    ): JsonResponse {
        $this->authorize('create', SupportTicket::class);

        $model = $service->create(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'data' => $model,
        ], 201);
    }

    public function show(
        SupportTicket $supportTicket
    ): JsonResponse {
        $this->authorize('view', $supportTicket);

        $supportTicket->load([
            'supportCategory',
            'assignedTo:id,first_name,last_name,mobile',
            'supportMessages' => fn ($query) => $query
                ->where('is_internal', false)
                ->with('user:id,first_name,last_name')
                ->oldest('id'),
        ]);

        return response()->json([
            'data' => $supportTicket,
        ]);
    }

    public function update(
        UpdateSupportTicketRequest $request,
        SupportTicket $supportTicket,
        SupportTicketWorkflowService $service
    ): JsonResponse {
        $this->authorize('update', $supportTicket);

        return response()->json([
            'data' => $service->updateDetails(
                $supportTicket,
                $request->user(),
                $request->validated()
            ),
        ]);
    }

    public function destroy(
        SupportTicket $supportTicket
    ): JsonResponse {
        $this->authorize('delete', $supportTicket);
        $supportTicket->delete();

        return response()->json(status: 204);
    }
}
