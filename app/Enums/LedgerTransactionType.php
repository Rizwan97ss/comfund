<?php

namespace App\Enums;

enum LedgerTransactionType: string
{
    case Contribution = 'contribution';
    case Expense = 'expense';
    case AccountTransfer = 'account_transfer';
    case OpeningBalance = 'opening_balance';
    case Reversal = 'reversal';
}
