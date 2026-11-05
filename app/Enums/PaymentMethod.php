<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Upi = 'upi';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Card = 'card';
    case Other = 'other';
}
