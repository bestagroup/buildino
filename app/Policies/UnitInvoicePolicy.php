<?php

namespace App\Policies;

class UnitInvoicePolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'invoices';
    }
    public function issue(\App\Models\User $user, \App\Models\UnitInvoice $invoice): bool
    {
        return $this->permissions->allows($user, 'invoices.issue', $this->scope($invoice));
    }

    public function adjust(\App\Models\User $user, \App\Models\UnitInvoice $invoice): bool
    {
        return $this->permissions->allows($user, 'invoices.adjust', $this->scope($invoice));
    }
}
