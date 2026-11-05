<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use App\Support\BackupCipher;
use Illuminate\Console\Command;

class DecryptBackupCommand extends Command
{
    protected $signature = 'backup:decrypt {source : Path to the .sql.gz.enc file} {destination : Where to write the plain .sql file}';

    protected $description = 'Decrypt a backup so that it can be restored.';

    public function handle(AuditLogger $audit): int
    {
        $source = (string) $this->argument('source');

        if (! is_file($source)) {
            $this->error('That backup file does not exist.');

            return self::FAILURE;
        }

        file_put_contents((string) $this->argument('destination'), BackupCipher::decrypt((string) file_get_contents($source)));

        $audit->record('backup.decrypted', 'security', 'warning', payload: ['source' => basename($source)]);
        $this->info('Backup decrypted. Delete the plain file after restoring it.');

        return self::SUCCESS;
    }
}
