<?php

namespace App\Services;

use App\Enums\SupportTicketStatus;
use App\Events\SupportTicketAssigned;
use App\Events\SupportTicketResolved;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SupportTicketService
{
    public function assign(SupportTicket $ticket, User $assignee): SupportTicket
    {
        return DB::transaction(function () use ($ticket, $assignee): SupportTicket {
            $ticket->update([
                'assigned_to' => $assignee->getKey(),
                'assigned_at' => now(),
                'status' => SupportTicketStatus::Assigned,
            ]);

            $assigned = $ticket->refresh();

            DB::afterCommit(
                fn () => SupportTicketAssigned::dispatch($assigned)
            );

            return $assigned;
        });
    }

    public function resolve(SupportTicket $ticket, string $resolution): SupportTicket
    {
        return DB::transaction(function () use ($ticket, $resolution): SupportTicket {
            $ticket->update([
                'status' => SupportTicketStatus::Resolved,
                'resolution' => $resolution,
                'resolved_at' => now(),
            ]);

            $resolved = $ticket->refresh();

            DB::afterCommit(
                fn () => SupportTicketResolved::dispatch($resolved)
            );

            return $resolved;
        });
    }
}
