<?php

namespace App\Enums;

enum PaymentGatewayEventType: string
{
    case Callback = 'callback';
    case Webhook = 'webhook';
}
