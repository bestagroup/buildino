<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResolveSupportTicketRequest;
use App\Http\Requests\StoreSupportMessageRequest;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\SupportTicketWorkflowService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketOperationController extends Controller
{
    public function assign(
        Request $request,
        SupportTicket $supportTicket,
        SupportTicketWorkflowService $service
    ): JsonResponse {
        $this->authorize('update', $supportTicket);

        $data = $request->validate([
            'assigned_to' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ]);

        $assignee = User::query()->findOrFail(
            $data['assigned_to']
        );

        return response()->json([
            'data' => $service->assign(
                $supportTicket,
                $assignee
            ),
        ]);
    }

    public function messages(
        Request $request,
        SupportTicket $supportTicket,
        PermissionChecker $permissions
    ): JsonResponse {
        $this->authorize('view', $supportTicket);

        $canSeeInternal = $permissions->allows(
            $request->user(),
            'support-tickets.update',
            $supportTicket->building
        );

        $messages = $supportTicket
            ->supportMessages()
            ->with('user:id,first_name,last_name')
            ->when(
                ! $canSeeInternal,
                fn ($query) => $query->where(
                    'is_internal',
                    false
                )
            )
            ->oldest('id')
            ->paginate(
                min(
                    max((int) $request->integer('per_page', 50), 1),
                    100
                )
            );

        return response()->json($messages);
    }

    public function addMessage(
        StoreSupportMessageRequest $request,
        SupportTicket $supportTicket,
        SupportTicketWorkflowService $service,
        PermissionChecker $permissions
    ): JsonResponse {
        $this->authorize('view', $supportTicket);

        $internal = (bool) $request->validated(
            'is_internal',
            false
        );

        if ($internal) {
            abort_unless(
                $permissions->allows(
                    $request->user(),
                    'support-tickets.update',
                    $supportTicket->building
                ),
                403
            );
        }

        return response()->json([
            'data' => $service->addMessage(
                $supportTicket,
                $request->user(),
                $request->validated('message'),
                $internal
            ),
        ], 201);
    }

    public function start(
        Request $request,
        SupportTicket $supportTicket,
        SupportTicketWorkflowService $service
    ): JsonResponse {
        $this->authorize('update', $supportTicket);

        return response()->json([
            'data' => $service->start($supportTicket),
        ]);
    }

    public function waitForUser(
        Request $request,
        SupportTicket $supportTicket,
        SupportTicketWorkflowService $service
    ): JsonResponse {
        $this->authorize('update', $supportTicket);

        return response()->json([
            'data' => $service->waitForUser($supportTicket),
        ]);
    }

    public function resolve(
        ResolveSupportTicketRequest $request,
        SupportTicket $supportTicket,
        SupportTicketWorkflowService $service
    ): JsonResponse {
        $this->authorize('update', $supportTicket);

        return response()->json([
            'data' => $service->resolve(
                $supportTicket,
                $request->validated('resolution')
            ),
        ]);
    }

    public function close(
        Request $request,
        SupportTicket $supportTicket,
        SupportTicketWorkflowService $service
    ): JsonResponse {
        $this->authorize('update', $supportTicket);

        return response()->json([
            'data' => $service->close($supportTicket),
        ]);
    }

    public function reopen(
        Request $request,
        SupportTicket $supportTicket,
        SupportTicketWorkflowService $service
    ): JsonResponse {
        $allowed = (int) $supportTicket->user_id
                === (int) $request->user()->getKey()
            || $request->user()->can(
                'update',
                $supportTicket
            );

        abort_unless($allowed, 403);

        return response()->json([
            'data' => $service->reopen($supportTicket),
        ]);
    }
}
