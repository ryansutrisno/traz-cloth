<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_passes_when_scheduler_heartbeat_is_recent(): void
    {
        $this->heartbeat();

        $this->artisan('app:health-check')
            ->expectsOutputToContain('Health check OK')
            ->assertExitCode(0);
    }

    public function test_health_check_fails_when_heartbeat_is_missing(): void
    {
        Cache::forget('scheduler:heartbeat');

        $this->artisan('app:health-check')
            ->expectsOutputToContain('Heartbeat is missing.')
            ->assertExitCode(1);
    }

    public function test_health_check_fails_when_heartbeat_is_stale(): void
    {
        Cache::put('scheduler:heartbeat', now()->subMinutes(10)->toIso8601String(), 3600);

        $this->artisan('app:health-check')
            ->expectsOutputToContain('Heartbeat is older than 5 minutes.')
            ->assertExitCode(1);
    }

    public function test_failed_jobs_only_warn_unless_strict_mode_is_used(): void
    {
        $this->heartbeat();
        $this->insertFailedJob();

        $this->artisan('app:health-check')
            ->expectsOutputToContain('1 failed job(s) in the last 24 hours.')
            ->assertExitCode(0);

        $this->artisan('app:health-check', ['--strict' => true])
            ->assertExitCode(1);
    }

    public function test_queue_backlog_is_reported_and_strict_mode_fails(): void
    {
        $this->heartbeat();
        $this->insertBackloggedJob();

        $this->artisan('app:health-check')
            ->expectsOutputToContain('1 job(s) exceed the configured age.')
            ->assertExitCode(0);

        $this->artisan('app:health-check', ['--strict' => true])
            ->assertExitCode(1);
    }

    private function heartbeat(): void
    {
        Cache::put('scheduler:heartbeat', now()->toIso8601String(), 3600);
    }

    private function insertFailedJob(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) str()->uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'RuntimeException: boom',
            'failed_at' => now(),
        ]);
    }

    private function insertBackloggedJob(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subMinutes(30)->timestamp,
            'created_at' => now()->subMinutes(30)->timestamp,
        ]);
    }
}
