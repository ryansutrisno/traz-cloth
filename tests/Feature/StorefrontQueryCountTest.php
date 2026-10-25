<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignSize;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorefrontQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_and_campaign_detail_remain_within_query_budget(): void
    {
        $campaigns = Campaign::factory()->count(6)->create();
        $campaigns->each(function (Campaign $campaign): void {
            CampaignSize::factory()->for($campaign)->s()->create();
        });

        $queryCount = 0;
        DB::listen(function (QueryExecuted $query) use (&$queryCount): void {
            $queryCount++;
        });

        $homeResponse = $this->get(route('home'));
        $homeQueryCount = $queryCount;
        $queryCount = 0;
        $detailResponse = $this->get(route('campaigns.show', $campaigns->firstOrFail()->slug));

        $homeResponse->assertOk();
        $detailResponse->assertOk();

        // Eager loading keeps six featured campaigns and one detail page under five queries each.
        self::assertLessThanOrEqual(5, $homeQueryCount);
        self::assertLessThanOrEqual(5, $queryCount);
    }
}
