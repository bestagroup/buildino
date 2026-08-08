<?php

namespace App\Policies;

class SupportTicketPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'support-tickets';
    }
}
