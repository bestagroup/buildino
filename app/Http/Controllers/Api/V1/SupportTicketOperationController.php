<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketOperationController extends Controller
{
    public function assign(Request $request, SupportTicket $supportTicket, SupportTicketService $service): JsonResponse
    {
        $this->authorize('update', $supportTicket);
        $data = $request->validate(['assigned_to' => ['required', 'integer', 'exists:users,id']]);
        $assignee = User::query()->findOrFail($data['assigned_to']);
        return response()->json(['data' => $service->assign($supportTicket, $assignee)]);
    }

    public function resolve(Request $request, SupportTicket $supportTicket, SupportTicketService $service): JsonResponse
    {
        $this->authorize('update', $supportTicket);
        $data = $request->validate(['resolution' => ['required', 'string', 'max:10000']]);
        return response()->json(['data' => $service->resolve($supportTicket, $data['resolution'])]);
    }
}
