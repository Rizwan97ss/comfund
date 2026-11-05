<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use App\Services\LedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileLedgerCommand extends Command
{
    protected $signature = 'ledger:reconcile';

    protected $description = 'Prove that running balances equal a full recomputation from the ledger.';

    public function handle(LedgerService $ledger, AuditLogger $audit): int
    {
        $result = $ledger->reconcileBalances();

        if (! $result['balanced']) {
            Log::critical('Ledger reconciliation failed', $result);
            $audit->record('ledger.reconciliation_failed', 'security', 'critical', payload: $result);
            $this->error('Balances differ from the ledger.');
            $this->table(array_keys($result['differences'][0]), $result['differences']);

            return self::FAILURE;
        }

        $audit->record('ledger.reconciled', 'finance', payload: ['ledger_total_paise' => $result['ledger_total_paise']]);
        $this->info('All balances match the ledger.');

        return self::SUCCESS;
    }
}
