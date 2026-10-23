<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CampaignImageStoreRequest;
use App\Models\Campaign;
use App\Models\CampaignImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CampaignImageController extends Controller
{
    public function store(CampaignImageStoreRequest $request, Campaign $campaign): RedirectResponse
    {
        /** @var array<int, UploadedFile> $files */
        $files = $request->file('images', []);
        $existing = $campaign->images()->count();

        if ($existing + count($files) > 8) {
            return back()->withErrors([
                'images' => 'Maksimal 8 foto per kampanye.',
            ]);
        }

        $sortOrder = (int) $campaign->images()->max('sort_order');
        $wasEmpty = $existing === 0;

        foreach ($files as $index => $file) {
            $path = Storage::disk('public')->putFile('campaigns', $file);

            if ($path === false || $path === null) {
                throw new RuntimeException('Gambar kampanye gagal disimpan.');
            }

            CampaignImage::create([
                'campaign_id' => $campaign->id,
                'path' => $path,
                'is_primary' => $wasEmpty && $index === 0,
                'sort_order' => ++$sortOrder,
            ]);
        }

        $this->syncPrimaryImage($campaign);

        return back()->with('status', 'Foto kampanye ditambahkan.');
    }

    public function destroy(Campaign $campaign, CampaignImage $image): RedirectResponse
    {
        abort_unless($image->campaign_id === $campaign->id, 404);

        if (! Str::startsWith($image->path, ['http://', 'https://'])) {
            Storage::disk('public')->delete($image->path);
        }

        $image->delete();
        $this->syncPrimaryImage($campaign);

        return back()->with('status', 'Foto kampanye dihapus.');
    }

    public function primary(Campaign $campaign, CampaignImage $image): RedirectResponse
    {
        abort_unless($image->campaign_id === $campaign->id, 404);

        DB::transaction(function () use ($campaign, $image): void {
            $campaign->images()->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);
            $this->syncPrimaryImage($campaign);
        });

        return back()->with('status', 'Foto utama kampanye diperbarui.');
    }

    private function syncPrimaryImage(Campaign $campaign): void
    {
        $primary = $campaign->images()
            ->where('is_primary', true)
            ->first()
            ?? $campaign->images()->orderBy('sort_order')->orderBy('id')->first();

        if ($primary === null || $campaign->getRawOriginal('image_url') === $primary->path) {
            return;
        }

        $campaign->forceFill(['image_url' => $primary->path])->save();
    }
}
