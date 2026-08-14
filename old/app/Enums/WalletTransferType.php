<?php

namespace App\Enums;

enum WalletTransferType: string
{
    case TopUp = 'topup';
    case InternalTransfer = 'internal_transfer';
    case ChargeCollection = 'charge_collection';
    case FacilityFee = 'facility_fee';
    case ServiceProviderPayment = 'service_provider_payment';
    case PlatformCommission = 'platform_commission';
    case BillPayment = 'bill_payment';
    case Payout = 'payout';
    case ProviderPayout = 'provider_payout';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
}
