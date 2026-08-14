<?php

namespace App\Services;

use App\Models\UnitInvoice;
use App\Models\User;
use App\Services\Security\UnitResidentAccessService;
use App\Support\Authorization\PermissionChecker;

final class InvoiceAccessService
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly UnitResidentAccessService $residentAccess
    ) {}

    public function canView(User $user, UnitInvoice $invoice): bool
    {
        $invoice->loadMissing(['building','unit']);

        if (
            $invoice->building
            && $this->permissions->allows(
                $user,
                'invoices.view',
                $invoice->building
            )
        ) {
            return true;
        }

        return $invoice->unit
            ? $this->residentAccess->allows($user, $invoice->unit)
            : false;
    }

    public function canPay(User $user, UnitInvoice $invoice): bool
    {
        $invoice->loadMissing(['building','unit']);

        if (
            $invoice->building
            && $this->permissions->allows(
                $user,
                'payments.create',
                $invoice->building
            )
        ) {
            return true;
        }

        return $invoice->unit
            ? $this->residentAccess->allows($user, $invoice->unit)
            : false;
    }
}
