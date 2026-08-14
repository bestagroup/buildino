<?php

namespace App\Enums;

enum ServiceRequestQuoteStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
