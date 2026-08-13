<?php

namespace App\Enums;

enum WalletEntryType: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
