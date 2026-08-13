<?php

namespace App\Enums;

enum BuildingBillType: string
{
    case Electricity = 'electricity';
    case Water = 'water';
    case Gas = 'gas';
    case Phone = 'phone';
    case Internet = 'internet';
    case Municipality = 'municipality';
    case Other = 'other';
}
