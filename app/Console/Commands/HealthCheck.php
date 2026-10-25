<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('app:health-check {--strict} {--max-backlog-age=10}')]
#[Description('Periksa kesehatan komponen penting aplikasi.')]
class HealthCheck extends Command
{
    /** @var array<int, array{check: string, status: string, message: string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->checkDatabase();
        $this->checkSchedulerHeartbeat();
        $this->checkQueueBacklog();
        $this->checkFailedJobs();
        $this->checkStorage();
        $this->checkEnvironment();

        $this->table(['Check', 'Status', 'Detail'], array_map(
            fn (array $result): array => [$result['check'], $result['status'], $result['message']],
            $this->results,
        ));

        $hasFailure = collect($this->results)->contains(fn (array $result): bool => $result['status'] === 'FAIL');

        if ($hasFailure) {
            $this->error('Health check FAILED');

            return self::FAILURE;
        }

        $this->info('Health check OK');

        return self::SUCCESS;
    }

    private function checkDatabase(): void
    {
        try {
            DB::select('select 1');
            $this->result('Database', 'OK', 'Database connection is available.');
        } catch (Throwable $exception) {
            $this->result('Database', 'FAIL', $exception->getMessage());
        }
    }

    private function checkSchedulerHeartbeat(): void
    {
        try {
            $heartbeat = Cache::get('scheduler:heartbeat');
        } catch (Throwable $exception) {
            $this->result('Scheduler heartbeat', 'FAIL', $exception->getMessage());

            return;
        }

        if (! is_string($heartbeat) || $heartbeat === '') {
            $this->result('Scheduler heartbeat', 'FAIL', 'Heartbeat is missing.');

            return;
        }

        try {
            if (Carbon::parse($heartbeat)->lt(now()->subMinutes(5))) {
                $this->result('Scheduler heartbeat', 'FAIL', 'Heartbeat is older than 5 minutes.');

                return;
            }

            $this->result('Scheduler heartbeat', 'OK', 'Heartbeat is recent.');
        } catch (Throwable $exception) {
            $this->result('Scheduler heartbeat', 'FAIL', 'Invalid heartbeat: '.$exception->getMessage());
        }
    }

    private function checkQueueBacklog(): void
    {
        try {
            $age = max(0, (int) $this->option('max-backlog-age'));
            $count = DB::table('jobs')->where('created_at', '<', now()->subMinutes($age)->timestamp)->count();
            $status = $count > 0 ? ($this->option('strict') ? 'FAIL' : 'WARN') : 'OK';

            $this->result('Queue backlog', $status, $count.' job(s) exceed the configured age.');
        } catch (Throwable $exception) {
            $this->result('Queue backlog', 'FAIL', $exception->getMessage());
        }
    }

    private function checkFailedJobs(): void
    {
        try {
            $count = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
            $status = $count > 0 ? ($this->option('strict') ? 'FAIL' : 'WARN') : 'OK';

            $this->result('Failed jobs', $status, $count.' failed job(s) in the last 24 hours.');
        } catch (Throwable $exception) {
            $this->result('Failed jobs', 'FAIL', $exception->getMessage());
        }
    }

    private function checkStorage(): void
    {
        $paths = [
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/logs' => storage_path('logs'),
        ];
        $unwritable = array_keys(array_filter($paths, fn (string $path): bool => ! is_writable($path)));

        $this->result(
            'Storage',
            $unwritable === [] ? 'OK' : 'FAIL',
            $unwritable === [] ? 'Required directories are writable.' : 'Not writable: '.implode(', ', $unwritable),
        );
    }

    private function checkEnvironment(): void
    {
        $isProduction = config('app.env') === 'production';
        $hasWarning = false;

        if ($isProduction && (bool) config('app.debug')) {
            $this->result('Environment', 'WARN', 'APP_DEBUG is enabled in production.');
            $hasWarning = true;
        }

        if ($isProduction && config('queue.default') === 'sync') {
            $this->result('Environment', 'WARN', 'The production queue driver is sync.');
            $hasWarning = true;
        }

        if (! $hasWarning) {
            $this->result('Environment', 'OK', 'Environment configuration is safe.');
        }
    }

    private function result(string $check, string $status, string $message): void
    {
        $this->results[] = compact('check', 'status', 'message');
    }
}
