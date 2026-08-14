<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\Size;
use App\Models\Campaign;
use App\Models\CampaignSize;
use App\Models\CartItem;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

class CartService
{
    public function __construct(
        private PricingService $pricingService,
        private QuotaService $quotaService,
    ) {}

    /**
     * Get the user's cart with effective prices and item subtotals attached.
     *
     * @return Collection<int, CartItem>
     */
    public function itemsFor(User $user): Collection
    {
        return CartItem::query()
            ->where('user_id', $user->id)
            ->with('campaign.sizes')
            ->get()
            ->each(function (CartItem $item): void {
                $size = $this->findCampaignSize($item->campaign, $item->size, false);
                $price = $this->priceFor($item->campaign, $size);

                $item->setAttribute('price', $price);
                $item->setAttribute('subtotal', $price * $item->qty);
                $item->setRelation('campaignSize', $size);
            });
    }

    public function add(User $user, int $campaignId, Size|string $size, int $qty): CartItem
    {
        if ($qty < 1) {
            throw new DomainException('Jumlah produk minimal satu.');
        }

        $normalizedSize = $this->normalizeSize($size);
        $campaign = Campaign::query()->find($campaignId);

        if ($campaign === null || $campaign->status !== CampaignStatus::Active) {
            throw new DomainException('Kampanye tidak tersedia.');
        }

        $campaignSize = $this->findCampaignSize($campaign, $normalizedSize);
        $attributes = [
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'size' => $normalizedSize->value,
        ];
        $currentQty = (int) (CartItem::query()->where($attributes)->value('qty') ?? 0);
        $newQty = $currentQty + $qty;

        $this->assertAvailableQuantity($campaign, $newQty);

        $cartItem = CartItem::query()->updateOrCreate($attributes, ['qty' => $newQty]);

        return $cartItem->load(['campaign', 'campaign.sizes']);
    }

    public function updateQty(User $user, int $cartItemId, int $qty): CartItem
    {
        if ($qty < 1) {
            throw new DomainException('Jumlah produk minimal satu.');
        }

        $cartItem = CartItem::query()
            ->where('user_id', $user->id)
            ->findOrFail($cartItemId);
        $campaign = $cartItem->campaign()->firstOrFail();

        $this->assertCampaignActive($campaign);
        $this->findCampaignSize($campaign, $cartItem->size);
        $this->assertAvailableQuantity($campaign, $qty);

        $cartItem->update(['qty' => $qty]);

        return $cartItem->load(['campaign', 'campaign.sizes']);
    }

    public function remove(User $user, int $cartItemId): void
    {
        CartItem::query()
            ->where('user_id', $user->id)
            ->findOrFail($cartItemId)
            ->delete();
    }

    public function clear(User $user): void
    {
        CartItem::query()->where('user_id', $user->id)->delete();
    }

    public function total(User $user): int
    {
        return $this->itemsFor($user)->sum(
            fn (CartItem $item): int => (int) $item->getAttribute('subtotal'),
        );
    }

    private function normalizeSize(Size|string $size): Size
    {
        if ($size instanceof Size) {
            return $size;
        }

        $normalizedSize = Size::tryFrom(strtoupper($size));

        if ($normalizedSize === null) {
            throw new DomainException('Ukuran produk tidak valid.');
        }

        return $normalizedSize;
    }

    private function assertCampaignActive(Campaign $campaign): void
    {
        if ($campaign->status !== CampaignStatus::Active) {
            throw new DomainException('Kampanye tidak tersedia.');
        }
    }

    private function assertAvailableQuantity(Campaign $campaign, int $qty): void
    {
        if ($this->quotaService->availableSlots($campaign) < $qty) {
            throw new DomainException('Kuota kampanye tidak mencukupi.');
        }
    }

    private function findCampaignSize(Campaign $campaign, Size $size, bool $requireEnabled = true): CampaignSize
    {
        $campaignSize = $campaign->relationLoaded('sizes')
            ? $campaign->sizes->first(fn (CampaignSize $item): bool => $item->size === $size)
            : CampaignSize::query()
                ->where('campaign_id', $campaign->id)
                ->where('size', $size->value)
                ->first();

        if ($campaignSize === null || ($requireEnabled && ! $campaignSize->enabled)) {
            throw new DomainException('Ukuran produk tidak tersedia.');
        }

        return $campaignSize;
    }

    private function priceFor(Campaign $campaign, CampaignSize $campaignSize): int
    {
        return $this->pricingService->priceFor(
            (float) $campaign->base_price,
            $campaignSize->size,
            $campaignSize->price_override === null ? null : (float) $campaignSize->price_override,
        );
    }
}
