<?php

namespace App\Enums;

enum WalletReconciliationStatus: string
{
    case Matched = 'matched';
    case Mismatch = 'mismatch';
}
