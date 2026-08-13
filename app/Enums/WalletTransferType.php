<?php

namespace App\Enums;

enum WalletTransferType: string
{
    case TopUp = 'topup';
    case InternalTransfer = 'internal_transfer';
    case ChargeCollection = 'charge_collection';
    case FacilityFee = 'facility_fee';
    case BillPayment = 'bill_payment';
    case Payout = 'payout';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
}
