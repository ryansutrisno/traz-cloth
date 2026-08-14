<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Throwable;

#[Signature('app:db-backup')]
#[Description('Buat backup PostgreSQL database aplikasi.')]
class DbBackup extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $backupDirectory = storage_path('app/backups');
        $backupPath = $backupDirectory.'/traz-cloth-'.now()->format('Y-m-d-His').'.dump';

        try {
            (new Filesystem)->ensureDirectoryExists($backupDirectory);

            $arguments = [
                'pg_dump',
                '--host='.(string) config('database.connections.pgsql.host'),
                '--port='.(string) config('database.connections.pgsql.port'),
                '--username='.(string) config('database.connections.pgsql.username'),
                '--dbname='.(string) config('database.connections.pgsql.database'),
                '--format=custom',
                '--file='.$backupPath,
            ];
            $command = implode(' ', array_map('escapeshellarg', $arguments));
            $result = Process::env([
                'PGPASSWORD' => (string) config('database.connections.pgsql.password'),
            ])->run($command);

            if (! $result->successful()) {
                $message = trim($result->errorOutput() ?: $result->output());

                $this->error('Backup database gagal: '.($message !== '' ? $message : 'pg_dump mengembalikan error.'));

                return self::FAILURE;
            }

            $this->removeExpiredBackups($backupDirectory);
            $size = filesize($backupPath);
            $sizeInKilobytes = $size === false ? 'unknown' : number_format($size / 1024, 2).' KB';

            $this->info("Backup berhasil: {$backupPath} ({$sizeInKilobytes})");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Backup database gagal: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function removeExpiredBackups(string $backupDirectory): void
    {
        $expiryTimestamp = now()->subDays(14)->getTimestamp();

        foreach (glob($backupDirectory.'/traz-cloth-*.dump') ?: [] as $backupPath) {
            if (is_file($backupPath) && filemtime($backupPath) < $expiryTimestamp) {
                unlink($backupPath);
            }
        }
    }
}
