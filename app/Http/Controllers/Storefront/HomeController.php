<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignSize;
use App\Services\PricingService;
use App\Services\QuotaService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(
        private PricingService $pricingService,
        private QuotaService $quotaService,
    ) {}

    public function index(Request $request): Response
    {
        $featuredCampaigns = Campaign::query()
            ->where('status', CampaignStatus::Active->value)
            ->with(['sizes' => fn (HasMany $query): HasMany => $query->where('enabled', true)])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Campaign $campaign): array => $this->campaignSummary($campaign))
            ->values()
            ->all();

        return Inertia::render('home', [
            'featured_campaigns' => $featuredCampaigns,
        ]);
    }

    /**
     * Transform a campaign into the summary shape used by the catalog.
     *
     * @return array{id: int, slug: string, title: string, image_url: string|null, harga_terendah: int|null, available: int, is_sold_out: bool, progress_percent: int, sizes_count: int}
     */
    private function campaignSummary(Campaign $campaign): array
    {
        $prices = $campaign->sizes->map(
            fn (CampaignSize $campaignSize): int => $this->pricingService->priceFor(
                (float) $campaign->base_price,
                $campaignSize->size,
                $campaignSize->price_override === null
                    ? null
                    : (float) $campaignSize->price_override,
            ),
        );

        return [
            'id' => $campaign->id,
            'slug' => $campaign->slug,
            'title' => $campaign->title,
            'image_url' => $campaign->image_url,
            'harga_terendah' => $prices->isEmpty() ? null : $prices->min(),
            'available' => $this->quotaService->availableSlots($campaign),
            'is_sold_out' => $this->quotaService->isSoldOut($campaign),
            'progress_percent' => $this->quotaService->progressPercent($campaign),
            'sizes_count' => $campaign->sizes->count(),
        ];
    }
}
