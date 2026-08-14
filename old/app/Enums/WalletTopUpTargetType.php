<?php

namespace App\Enums;

enum WalletTopUpTargetType: string
{
    case UserWallet = 'user_wallet';
    case UnitWallet = 'unit_wallet';
}
