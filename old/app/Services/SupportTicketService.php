<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\SupportTicketWorkflowService;

/**
 * Backward-compatible domain façade kept for existing callers/tests.
 */
class SupportTicketService
{
    public function __construct(
        private readonly SupportTicketWorkflowService $workflow
    ) {
    }

    public function assign(SupportTicket $ticket, User $assignee): SupportTicket
    {
        return $this->workflow->assign($ticket, $assignee);
    }

    public function resolve(SupportTicket $ticket, string $resolution): SupportTicket
    {
        return $this->workflow->resolve($ticket, $resolution);
    }
}
