<?php

namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('campaigns:check-sold-out')]
#[Description('Sinkronkan status kampanye yang sudah sold out.')]
class CheckCampaignSoldOut extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Campaign::query()
            ->where('status', CampaignStatus::Active->value)
            ->whereColumn('sold_count', '>=', 'quota')
            ->update(['status' => CampaignStatus::SoldOut]);

        return self::SUCCESS;
    }
}
