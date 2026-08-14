<?php

namespace App\Enums;

enum ExpenseAllocationMethod: string
{
    case Equal = 'equal';
    case Area = 'area';
    case Persons = 'persons';
    case Custom = 'custom';
}
