<?php

namespace App\Enums;

enum UnitChargePayerSource: string
{
    case UnitWallet = 'unit_wallet';
    case UserWallet = 'user_wallet';
}
