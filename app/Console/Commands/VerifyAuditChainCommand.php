<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyAuditChainCommand extends Command
{
    protected $signature = 'audit:verify';

    protected $description = 'Recompute the audit log hash chain and report any tampering.';

    public function handle(AuditLogger $audit): int
    {
        $result = $audit->verifyChain();

        // The checkpoint in the application log makes truncating the end of the chain detectable.
        Log::info('Audit chain checkpoint', $result);

        if (! $result['valid']) {
            Log::critical('Audit chain verification failed', $result);
            $audit->record('audit.chain_broken', 'security', 'critical', payload: $result);
            $this->error("Audit chain broken at sequence {$result['broken_at']}: {$result['reason']}");

            return self::FAILURE;
        }

        $audit->record('audit.chain_verified', 'security', payload: ['checked' => $result['checked']]);
        $this->info("Audit chain intact: {$result['checked']} entries checked.");

        return self::SUCCESS;
    }
}
