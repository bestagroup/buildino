<?php

namespace App\Enums;

enum PaymentGatewayEventStatus: string
{
    case Received = 'received';
    case Processed = 'processed';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
