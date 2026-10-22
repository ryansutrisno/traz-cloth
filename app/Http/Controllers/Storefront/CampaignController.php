<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\CampaignStatus;
use App\Enums\Size;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignSize;
use App\Services\PricingService;
use App\Services\QuotaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function __construct(
        private PricingService $pricingService,
        private QuotaService $quotaService,
    ) {}

    public function index(Request $request): Response
    {
        $request->validate([
            'size' => ['nullable', Rule::enum(Size::class)],
            'status' => ['nullable', Rule::in(['ready', 'sold_out'])],
        ]);

        $search = $request->string('q')->trim()->toString();
        $size = $request->string('size')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $campaigns = Campaign::query()
            ->whereIn('status', [CampaignStatus::Active->value, CampaignStatus::SoldOut->value])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('title', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->when($size !== '', function (Builder $query) use ($size): void {
                $query->whereHas('sizes', function (Builder $query) use ($size): void {
                    $query
                        ->where('size', $size)
                        ->where('enabled', true);
                });
            })
            ->when($status === 'ready', function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query
                        ->where(function (Builder $query): void {
                            $query
                                ->where('status', CampaignStatus::Active->value)
                                ->whereColumn('sold_count', '<', 'quota');
                        })
                        ->orWhereHas('sizes', fn (Builder $query): Builder => $query->where('stock', '>', 0));
                });
            })
            ->when($status === 'sold_out', function (Builder $query): void {
                $query
                    ->whereColumn('sold_count', '>=', 'quota')
                    ->whereDoesntHave('sizes', fn (Builder $query): Builder => $query->where('stock', '>', 0));
            })
            ->with(['sizes' => fn (HasMany $query): HasMany => $query->where('enabled', true)])
            ->paginate(12)
            ->withQueryString();

        $campaignData = $campaigns->getCollection()
            ->map(fn (Campaign $campaign): array => $this->campaignSummary($campaign))
            ->values()
            ->all();

        return Inertia::render('campaigns/index', [
            'campaigns' => [
                'data' => $campaignData,
            ],
            'filters' => [
                'q' => $search,
                'size' => $size,
                'status' => $status,
            ],
            'pagination' => [
                'current_page' => $campaigns->currentPage(),
                'last_page' => $campaigns->lastPage(),
                'per_page' => $campaigns->perPage(),
                'total' => $campaigns->total(),
                'from' => $campaigns->firstItem(),
                'to' => $campaigns->lastItem(),
                'links' => $campaigns->linkCollection()->toArray(),
            ],
        ]);
    }

    public function show(string $slug): Response
    {
        $campaign = Campaign::query()
            ->where('slug', $slug)
            ->whereIn('status', [CampaignStatus::Active->value, CampaignStatus::SoldOut->value])
            ->with(['sizes' => fn (HasMany $query): HasMany => $query->where('enabled', true)])
            ->firstOrFail();

        $basePrice = (float) $campaign->base_price;
        $priceBySize = $campaign->sizes
            ->mapWithKeys(function (CampaignSize $campaignSize) use ($basePrice): array {
                $price = $this->pricingService->priceFor(
                    $basePrice,
                    $campaignSize->size,
                    $campaignSize->price_override === null
                        ? null
                        : (float) $campaignSize->price_override,
                );

                return [
                    $campaignSize->size->value => [
                        'price' => $price,
                        'price_breakdown' => $this->pricingService->priceBreakdown(
                            $basePrice,
                            $campaignSize->size,
                            $campaignSize->price_override === null
                                ? null
                                : (float) $campaignSize->price_override,
                        ),
                        'stock_ready' => $campaignSize->stock,
                    ],
                ];
            })
            ->all();

        return Inertia::render('campaigns/show', [
            'campaign' => [
                'id' => $campaign->id,
                'slug' => $campaign->slug,
                'title' => $campaign->title,
                'description' => $campaign->description,
                'image_url' => $campaign->image_url,
                'base_price' => (float) $campaign->base_price,
                'quota' => $campaign->quota,
                'sold_count' => $campaign->sold_count,
                'status' => $campaign->status->value,
                'ready_stock_enabled' => $campaign->ready_stock_enabled,
            ],
            'price_by_size' => $priceBySize,
            'available' => $this->quotaService->availableSlots($campaign),
            'is_sold_out' => $this->quotaService->isSoldOut($campaign),
            'progress_percent' => $this->quotaService->progressPercent($campaign),
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
