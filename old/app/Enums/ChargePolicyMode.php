<?php

namespace App\Enums;

enum ChargePolicyMode: string
{
    case Fixed = 'fixed';
    case SharedExpenses = 'shared_expenses';
    case Mixed = 'mixed';
}
