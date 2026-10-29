<?php

namespace App\Support;

/** Presentation metadata for completed dashboard pages, independent of deployment config caches. */
final class ManagementPageCatalog
{
    public const STRUCTURE_RESOURCES = ['complexes', 'buildings', 'blocks', 'floors', 'units'];

    public const FINANCE_GROUPS = [
        ['title' => 'صورتحساب و دریافت', 'resources' => ['invoices', 'invoice-installments', 'payments']],
        ['title' => 'شارژ ساختمان', 'resources' => ['charge-formulas', 'charge-periods']],
        ['title' => 'عملیات ساختمان', 'resources' => ['expenses', 'incomes', 'bank-accounts', 'wallet-payouts', 'bill-payments']],
        ['title' => 'وفاداری', 'resources' => ['loyalty-rules', 'loyalty-rewards', 'loyalty-claims']],
    ];
}
