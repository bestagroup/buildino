<?php

namespace App\Enums;

enum WalletAccountingPostingStatus: string
{
    case Pending = 'pending';
    case Posted = 'posted';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
