<?php

namespace Tests\Feature;

use App\Enums\Size;
use App\Models\Campaign;
use App\Models\CampaignSize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CampaignStorefrontTest extends TestCase
{
    use RefreshDatabase;

    private Campaign $campaign;

    private Campaign $draftCampaign;

    private Campaign $searchCampaign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campaign = Campaign::factory()->create([
            'title' => 'Kaos Nusantara',
            'base_price' => 100000,
        ]);

        foreach (Size::cases() as $size) {
            CampaignSize::factory()
                ->for($this->campaign)
                ->create(['size' => $size->value]);
        }

        $this->draftCampaign = Campaign::factory()->draft()->create([
            'title' => 'Draft Campaign',
        ]);
        $this->searchCampaign = Campaign::factory()->create([
            'title' => 'Koleksi Nusantara Premium',
        ]);
    }

    public function test_catalog_lists_active_campaigns_only(): void
    {
        $response = $this->getInertia(route('campaigns.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/index')
            ->has('campaigns.data', 2)
            ->where(
                'campaigns.data',
                fn ($campaigns): bool => collect($campaigns)
                    ->pluck('slug')
                    ->doesntContain($this->draftCampaign->slug),
            ),
        );
    }

    public function test_catalog_can_search_by_keyword(): void
    {
        $response = $this->getInertia(route('campaigns.index', ['q' => 'Premium']));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/index')
            ->has('campaigns.data', 1)
            ->where('campaigns.data.0.slug', $this->searchCampaign->slug)
            ->where('filters.q', 'Premium'),
        );
    }

    public function test_catalog_can_filter_by_size(): void
    {
        $response = $this->getInertia(route('campaigns.index', ['size' => Size::S->value]));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('campaigns.data', 1)
            ->where('campaigns.data.0.slug', $this->campaign->slug)
            ->where('filters.size', Size::S->value),
        );
    }

    public function test_catalog_can_filter_ready_and_sold_out_campaigns(): void
    {
        $soldOutCampaign = Campaign::factory()->active()->create([
            'title' => 'Kampanye Sold Out',
            'quota' => 1,
            'sold_count' => 1,
        ]);
        CampaignSize::factory()->for($soldOutCampaign)->s()->create(['stock' => 0]);

        $readyResponse = $this->getInertia(route('campaigns.index', ['status' => 'ready']));
        $readyResponse->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', 'ready')
            ->where(
                'campaigns.data',
                fn ($campaigns): bool => collect($campaigns)
                    ->pluck('slug')
                    ->doesntContain($soldOutCampaign->slug),
            ),
        );

        $soldOutResponse = $this->getInertia(route('campaigns.index', ['status' => 'sold_out']));
        $soldOutResponse->assertInertia(fn (Assert $page) => $page
            ->has('campaigns.data', 1)
            ->where('campaigns.data.0.slug', $soldOutCampaign->slug)
            ->where('filters.status', 'sold_out'),
        );
    }

    public function test_campaign_detail_shows_sizes_with_effective_prices(): void
    {
        $response = $this->getInertia(route('campaigns.show', $this->campaign->slug));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('price_by_size.S.price', 100000)
            ->where('price_by_size.M.price', 105000)
            ->where('price_by_size.L.price', 105000)
            ->where('price_by_size.XL.price', 115000)
            ->where('price_by_size.XXL.price', 120000),
        );
    }

    public function test_campaign_detail_uses_price_override(): void
    {
        CampaignSize::query()
            ->where('campaign_id', $this->campaign->id)
            ->where('size', Size::M->value)
            ->update(['price_override' => 99999]);

        $response = $this->getInertia(route('campaigns.show', $this->campaign->slug));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('price_by_size.M.price', 99999),
        );
    }

    public function test_campaign_detail_sold_out_shows_flag(): void
    {
        $this->campaign->update(['sold_count' => $this->campaign->quota]);

        $response = $this->getInertia(route('campaigns.show', $this->campaign->slug));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('is_sold_out', true)
            ->where('available', 0),
        );
    }

    public function test_draft_campaign_detail_returns_404(): void
    {
        $response = $this->getInertia(route('campaigns.show', $this->draftCampaign->slug));

        $response->assertNotFound();
    }

    public function test_unknown_slug_returns_404(): void
    {
        $response = $this->getInertia(route('campaigns.show', 'unknown-campaign'));

        $response->assertNotFound();
    }

    public function test_catalog_shows_lowest_price(): void
    {
        $response = $this->getInertia(route('campaigns.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('campaigns.data.0.slug', $this->campaign->slug)
            ->where('campaigns.data.0.harga_terendah', 100000),
        );
    }

    private function getInertia(string $url): TestResponse
    {
        // Catatan: jangan kirim header X-Inertia. Dengan header itu Inertia v3
        // mengembalikan JSON murni, sedangkan AssertableInertia mengharapkan
        // full-page view berisi data 'page'.
        return $this->get($url);
    }
}
