<?php

namespace App\Enums;

enum FacilityWalletPayerSource: string
{
    case UserWallet = 'user_wallet';
    case UnitWallet = 'unit_wallet';
}
