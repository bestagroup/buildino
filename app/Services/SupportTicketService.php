<?php

namespace App\Services;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SupportTicketService
{
    public function assign(SupportTicket $ticket, User $assignee): SupportTicket
    {
        return DB::transaction(function () use ($ticket, $assignee): SupportTicket {
            $ticket->update(['assigned_to' => $assignee->getKey(), 'assigned_at' => now(), 'status' => SupportTicketStatus::Assigned]);
            return $ticket->refresh();
        });
    }

    public function resolve(SupportTicket $ticket, string $resolution): SupportTicket
    {
        return DB::transaction(function () use ($ticket, $resolution): SupportTicket {
            $ticket->update(['status' => SupportTicketStatus::Resolved, 'resolution' => $resolution, 'resolved_at' => now()]);
            return $ticket->refresh();
        });
    }
}
