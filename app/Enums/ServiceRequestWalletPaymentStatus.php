<?php

namespace App\Enums;

enum ServiceRequestWalletPaymentStatus: string
{
    case Locked = 'locked';
    case Settled = 'settled';
    case Released = 'released';
}
