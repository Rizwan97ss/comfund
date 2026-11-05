<?php

namespace App\Enums;

enum FinancialAccountType: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Digital = 'digital';
}
