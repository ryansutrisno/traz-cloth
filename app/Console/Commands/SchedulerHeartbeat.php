<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('app:heartbeat')]
#[Description('Simpan heartbeat scheduler.')]
class SchedulerHeartbeat extends Command
{
    public function handle(): int
    {
        Cache::put('scheduler:heartbeat', now()->toIso8601String(), 3600);
        $this->info('Scheduler heartbeat updated.');

        return self::SUCCESS;
    }
}
