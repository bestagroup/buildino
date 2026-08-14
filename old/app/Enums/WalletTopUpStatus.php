<?php

namespace App\Enums;

enum WalletTopUpStatus: string
{
    case Pending = 'pending';
    case Credited = 'credited';
    case Failed = 'failed';
}
