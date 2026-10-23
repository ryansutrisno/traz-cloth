<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CampaignStatus;
use App\Enums\Size;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CampaignStoreRequest;
use App\Http\Requests\Admin\CampaignUpdateRequest;
use App\Models\Campaign;
use App\Models\CampaignSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(CampaignStatus::class)],
        ]);

        $search = $request->string('q')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $campaigns = Campaign::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('title', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%');
                });
            })
            ->when($status !== '', fn (Builder $query): Builder => $query->where('status', $status))
            ->withCount('sizes')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/campaigns/index', [
            'campaigns' => [
                'data' => $campaigns->getCollection()
                    ->map(fn (Campaign $campaign): array => [
                        'id' => $campaign->id,
                        'slug' => $campaign->slug,
                        'title' => $campaign->title,
                        'image_url' => $campaign->image_url,
                        'base_price' => $campaign->base_price,
                        'quota' => $campaign->quota,
                        'sold_count' => $campaign->sold_count,
                        'status' => $campaign->status->value,
                        'created_at' => $campaign->created_at,
                        'sizes_count' => $campaign->sizes_count,
                    ])
                    ->values()
                    ->all(),
                'current_page' => $campaigns->currentPage(),
                'last_page' => $campaigns->lastPage(),
                'per_page' => $campaigns->perPage(),
                'total' => $campaigns->total(),
                'links' => $campaigns->linkCollection()->toArray(),
            ],
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/campaigns/create', [
            'sizes' => array_map(fn (Size $size): string => $size->value, Size::cases()),
            'statuses' => array_map(fn (CampaignStatus $status): string => $status->value, CampaignStatus::cases()),
            'cost_per_item' => null,
        ]);
    }

    public function store(CampaignStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $campaign = DB::transaction(function () use ($data, $request): Campaign {
            $image = $request->file('image');
            $imagePath = $image instanceof UploadedFile ? $this->storeImage($image) : null;

            $campaign = Campaign::create([
                'slug' => $this->uniqueSlug($data['slug'] ?: Str::slug($data['title'])),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'image_url' => $imagePath,
                'base_price' => $data['base_price'],
                'cost_per_item' => $data['cost_per_item'] ?? null,
                'quota' => $data['quota'],
                'sold_count' => 0,
                'status' => $data['status'],
                'ready_stock_enabled' => $request->boolean('ready_stock_enabled', true),
            ]);

            $this->storeSizes($campaign, $data['sizes']);

            return $campaign;
        });

        return redirect()
            ->route('admin.campaigns.edit', $campaign)
            ->with('status', 'Kampanye berhasil dibuat.');
    }

    public function edit(Campaign $campaign): Response
    {
        $campaign->load(['sizes' => fn (HasMany $query): HasMany => $query->orderBy('size')]);

        return Inertia::render('admin/campaigns/edit', [
            'campaign' => [
                'id' => $campaign->id,
                'slug' => $campaign->slug,
                'title' => $campaign->title,
                'description' => $campaign->description,
                'image_url' => $campaign->image_url,
                'base_price' => $campaign->base_price,
                'cost_per_item' => $campaign->cost_per_item,
                'quota' => $campaign->quota,
                'sold_count' => $campaign->sold_count,
                'status' => $campaign->status->value,
                'ready_stock_enabled' => $campaign->ready_stock_enabled,
                'sizes' => $campaign->sizes->map(fn (CampaignSize $campaignSize): array => [
                    'size' => $campaignSize->size->value,
                    'price_override' => $campaignSize->price_override,
                    'stock' => $campaignSize->stock,
                    'enabled' => $campaignSize->enabled,
                ])->values()->all(),
            ],
            'sizes' => array_map(fn (Size $size): string => $size->value, Size::cases()),
            'statuses' => array_map(fn (CampaignStatus $status): string => $status->value, CampaignStatus::cases()),
        ]);
    }

    public function update(CampaignUpdateRequest $request, Campaign $campaign): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request, $campaign): void {
            $campaignData = [
                'slug' => $this->uniqueSlug($data['slug'] ?: Str::slug($data['title']), $campaign->id),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'base_price' => $data['base_price'],
                'cost_per_item' => $data['cost_per_item'] ?? null,
                'quota' => $data['quota'],
                'status' => $data['status'],
                'ready_stock_enabled' => $request->boolean('ready_stock_enabled', true),
            ];

            $image = $request->file('image');

            if ($image instanceof UploadedFile) {
                $oldImagePath = $campaign->getRawOriginal('image_url');
                $campaignData['image_url'] = $this->storeImage($image);
                $campaign->update($campaignData);

                if ($this->isLocalImagePath($oldImagePath)) {
                    Storage::disk('public')->delete($oldImagePath);
                }
            } else {
                $campaign->update($campaignData);
            }

            foreach (Size::cases() as $size) {
                $sizeData = $data['sizes'][$size->value] ?? [];

                $campaign->sizes()->updateOrCreate(
                    ['size' => $size->value],
                    [
                        'price_override' => $sizeData['price_override'] ?? null,
                        'stock' => $sizeData['stock'] ?? 0,
                        'enabled' => $sizeData['enabled'] ?? false,
                    ],
                );
            }
        });

        return redirect()
            ->route('admin.campaigns.edit', $campaign)
            ->with('status', 'Kampanye berhasil diperbarui.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        if ($campaign->sold_count > 0) {
            return back()->withErrors([
                'sold_count' => 'Kampanye dengan pembayaran aktif tidak bisa dihapus.',
            ]);
        }

        $campaign->delete();

        return redirect()
            ->route('admin.campaigns.index')
            ->with('status', 'Kampanye dihapus.');
    }

    /**
     * Generate a unique campaign slug.
     */
    private function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $baseSlug = $slug;
        $suffix = 1;

        while (Campaign::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn (Builder $query): Builder => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $suffix++;
            $slug = $baseSlug.'-'.$suffix;
        }

        return $slug;
    }

    /**
     * Store all supported campaign sizes using the submitted configuration.
     *
     * @param  array<string, array<string, mixed>>  $sizes
     */
    private function storeSizes(Campaign $campaign, array $sizes): void
    {
        foreach (Size::cases() as $size) {
            $sizeData = $sizes[$size->value] ?? [];

            CampaignSize::create([
                'campaign_id' => $campaign->id,
                'size' => $size->value,
                'price_override' => $sizeData['price_override'] ?? null,
                'stock' => $sizeData['stock'] ?? 0,
                'enabled' => $sizeData['enabled'] ?? false,
            ]);
        }
    }

    private function storeImage(?UploadedFile $image): ?string
    {
        if ($image === null) {
            return null;
        }

        $path = Storage::disk('public')->putFile('campaigns', $image);

        if ($path === false) {
            throw new RuntimeException('Gambar kampanye gagal disimpan.');
        }

        return $path;
    }

    private function isLocalImagePath(?string $path): bool
    {
        return $path !== null && ! Str::startsWith($path, ['http://', 'https://']);
    }
}
