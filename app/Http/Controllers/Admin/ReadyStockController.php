<?php

namespace App\Http\Controllers\Admin;

use App\Models\CampaignSize;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReadyStockController
{
    public function index(Request $request): Response
    {
        $readyStock = CampaignSize::query()
            ->where('stock', '>', 0)
            ->with('campaign')
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();
        $readyStock->setCollection($readyStock->getCollection()->map(fn (CampaignSize $campaignSize): array => [
            'id' => $campaignSize->id,
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
}
