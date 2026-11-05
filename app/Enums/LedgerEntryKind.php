<?php

namespace App\Enums;

/**
 * What a ledger entry means for reporting. A reversal keeps the kind of the entry
 * it reverses with the opposite sign, so summing a kind always gives the net figure.
 */
enum LedgerEntryKind: string
{
    case Collection = 'collection';
    case Expense = 'expense';
    case Transfer = 'transfer';
    case Opening = 'opening';
}
