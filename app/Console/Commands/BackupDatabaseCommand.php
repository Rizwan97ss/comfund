<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use App\Support\BackupCipher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Write an encrypted dump of the database to private storage and prune old backups.';

    public function handle(AuditLogger $audit): int
    {
        if (blank(config('fund.backups.key'))) {
            $this->error('BACKUP_ENCRYPTION_KEY is not set, so no backup was made.');
            $audit->record('backup.failed', 'security', 'error', payload: ['reason' => 'missing key']);

            return self::FAILURE;
        }

        $connection = config('database.connections.'.config('database.default'));

        $result = Process::timeout(600)
            ->env(['MYSQL_PWD' => (string) ($connection['password'] ?? '')])
            ->run([
                (string) config('fund.backups.mysqldump_path'),
                '--single-transaction',
                '--routines',
                '--triggers',
                '--host='.($connection['host'] ?? ''),
                '--port='.($connection['port'] ?? ''),
                '--user='.($connection['username'] ?? ''),
                (string) ($connection['database'] ?? ''),
            ]);

        if ($result->failed() || $result->output() === '') {
            $this->error('mysqldump failed. Check MYSQLDUMP_PATH and the database credentials.');
            $audit->record('backup.failed', 'security', 'error', payload: ['exit_code' => $result->exitCode()]);

            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $directory = (string) config('fund.backups.directory');
        $path = $directory.'/fund-'.now()->format('Ymd-His').'.sql.gz.enc';

        $disk->put($path, BackupCipher::encrypt($result->output()));

        $cutoff = now()->subDays((int) config('fund.backups.retention_days'))->getTimestamp();
        $pruned = 0;

        foreach ($disk->files($directory) as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $pruned++;
            }
        }

        $audit->record('backup.completed', 'security', payload: ['path' => $path, 'bytes' => $disk->size($path), 'pruned' => $pruned]);
        $this->info("Encrypted backup written to {$path}.");

        return self::SUCCESS;
    }
}
