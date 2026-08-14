<?php

namespace App\Policies;

use App\Models\UnitInvoice;
use App\Models\User;

class UnitInvoicePolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'invoices';
    }

    public function issue(User $user, UnitInvoice $invoice): bool
    {
        return $this->permissions->allows(
            $user,
            'invoices.issue',
            $this->resolveScope($invoice)
        );
    }

    public function adjust(User $user, UnitInvoice $invoice): bool
    {
        return $this->permissions->allows(
            $user,
            'invoices.adjust',
            $this->resolveScope($invoice)
        );
    }
}
