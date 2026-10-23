<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminRole;
use App\Http\Requests\Admin\ReadyStockUpdateRequest;
use App\Models\CampaignSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReadyStockController
{
    public function index(Request $request): Response
    {
        $this->authorizeAdmin();

        $readyStock = CampaignSize::query()
            ->where('stock', '>', 0)
            ->with('campaign')
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();
        $readyStock->setCollection($readyStock->getCollection()->map(fn (CampaignSize $campaignSize): array => [
            'id' => $campaignSize->id,
            'campaign_id' => $campaignSize->campaign->id,
            'campaign_slug' => $campaignSize->campaign->slug,
            'campaign_title' => $campaignSize->campaign->title,
            'campaign' => [
                'id' => $campaignSize->campaign->id,
                'slug' => $campaignSize->campaign->slug,
                'title' => $campaignSize->campaign->title,
            ],
            'size' => $campaignSize->size->value,
            'stock' => $campaignSize->stock,
            'updated_at' => $campaignSize->updated_at,
        ])->values());

        return Inertia::render('admin/ready-stock', [
            'ready_stock' => $readyStock,
            'total_stock' => (int) CampaignSize::query()->where('stock', '>', 0)->sum('stock'),
        ]);
    }

    public function update(ReadyStockUpdateRequest $request, CampaignSize $campaignSize): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->ensureCampaignExists($campaignSize);

        $campaignSize->update(['stock' => $request->validated()['stock']]);

        return back()->with('status', 'Stok ready berhasil diperbarui.');
    }

    public function destroy(CampaignSize $campaignSize): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->ensureCampaignExists($campaignSize);

        $campaignSize->update(['stock' => 0]);

        return back()->with('status', 'Stok ready direset.');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Auth::guard('admin')->user()?->role === AdminRole::Admin, 403);
    }

    private function ensureCampaignExists(CampaignSize $campaignSize): void
    {
        abort_unless($campaignSize->campaign()->exists(), 404);
    }
}
