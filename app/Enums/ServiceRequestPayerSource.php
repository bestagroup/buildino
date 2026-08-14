<?php

namespace App\Enums;

enum ServiceRequestPayerSource: string
{
    case UserWallet = 'user_wallet';
    case UnitWallet = 'unit_wallet';
}
