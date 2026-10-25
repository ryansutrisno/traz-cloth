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
    private const GUEST_CART_KEY = 'guest_cart';

    public function __construct(
        private PricingService $pricingService,
        private QuotaService $quotaService,
    ) {}

    /**
     * Get a user's or guest's cart with effective prices attached.
     *
     * @return Collection<int, CartItem>
     */
    public function itemsFor(?User $user): Collection
    {
        if ($user === null) {
            return $this->guestItems();
        }

        return CartItem::query()
            ->where('user_id', $user->id)
            ->with('campaign.sizes')
            ->get()
            ->each(fn (CartItem $item): CartItem => $this->hydrateCartItem($item));
    }

    public function add(?User $user, int $campaignId, Size|string $size, int $qty): CartItem
    {
        if ($qty < 1) {
            throw new DomainException('Jumlah produk minimal satu.');
        }

        $normalizedSize = $this->normalizeSize($size);
        $campaign = Campaign::query()->with('sizes')->find($campaignId);

        if ($campaign === null) {
            throw new DomainException('Kampanye tidak tersedia.');
        }

        $this->assertCampaignPurchasable($campaign, $normalizedSize);

        if ($user === null) {
            $cart = $this->guestCart();
            $existingIndex = null;
            $currentQty = 0;

            foreach ($cart as $index => $item) {
                if ((int) ($item['campaign_id'] ?? 0) === $campaignId
                    && (string) ($item['size'] ?? '') === $normalizedSize->value) {
                    $existingIndex = (int) $index;
                    $currentQty = (int) ($item['qty'] ?? 0);
                    break;
                }
            }

            $newQty = $currentQty + $qty;
            $this->assertAvailableQuantity($campaign, $normalizedSize, $newQty);
            $index = $existingIndex ?? count($cart);
            $cart[$index] = [
                'campaign_id' => $campaignId,
                'size' => $normalizedSize->value,
                'qty' => $newQty,
            ];
            session()->put(self::GUEST_CART_KEY, $cart);

            return $this->guestCartItem($campaign, $normalizedSize, $newQty, $index);
        }

        return $this->addToDatabase($user, $campaign, $normalizedSize, $qty);
    }

    public function updateQty(?User $user, int $cartItemId, int $qty): CartItem
    {
        if ($qty < 1) {
            throw new DomainException('Jumlah produk minimal satu.');
        }

        if ($user === null) {
            $cart = $this->guestCart();
            $item = $cart[$cartItemId] ?? null;

            if (! is_array($item)) {
                throw new DomainException('Item keranjang tidak ditemukan.');
            }

            $size = $this->normalizeSize((string) ($item['size'] ?? ''));
            $campaign = Campaign::query()->with('sizes')->find((int) ($item['campaign_id'] ?? 0));

            if ($campaign === null) {
                throw new DomainException('Kampanye tidak tersedia.');
            }

            $this->assertCampaignPurchasable($campaign, $size);
            $this->assertAvailableQuantity($campaign, $size, $qty);
            $cart[$cartItemId]['qty'] = $qty;
            session()->put(self::GUEST_CART_KEY, $cart);

            return $this->guestCartItem($campaign, $size, $qty, $cartItemId);
        }

        $cartItem = CartItem::query()
            ->where('user_id', $user->id)
            ->findOrFail($cartItemId);
        $campaign = $cartItem->campaign()->with('sizes')->firstOrFail();

        $this->assertCampaignPurchasable($campaign, $cartItem->size);
        $this->assertAvailableQuantity($campaign, $cartItem->size, $qty);
        $cartItem->update(['qty' => $qty]);

        return $this->hydrateCartItem($cartItem->load(['campaign', 'campaign.sizes']));
    }

    public function remove(?User $user, int $cartItemId): void
    {
        if ($user === null) {
            $cart = $this->guestCart();

            if (! array_key_exists($cartItemId, $cart)) {
                throw new DomainException('Item keranjang tidak ditemukan.');
            }

            array_splice($cart, $cartItemId, 1);
            if ($cart === []) {
                session()->forget(self::GUEST_CART_KEY);
            } else {
                session()->put(self::GUEST_CART_KEY, $cart);
            }

            return;
        }

        CartItem::query()
            ->where('user_id', $user->id)
            ->findOrFail($cartItemId)
            ->delete();
    }

    public function clear(?User $user): void
    {
        if ($user === null) {
            session()->forget(self::GUEST_CART_KEY);

            return;
        }

        CartItem::query()->where('user_id', $user->id)->delete();
    }

    public function total(?User $user): int
    {
        return $this->itemsFor($user)->sum(
            fn (CartItem $item): int => (int) $item->getAttribute('subtotal'),
        );
    }

    public function countItems(?User $user): int
    {
        if ($user === null) {
            return (int) collect($this->guestCart())->sum('qty');
        }

        return (int) CartItem::query()->where('user_id', $user->id)->sum('qty');
    }

    public function mergeGuestCartToUser(User $user): void
    {
        $cart = $this->guestCart();

        try {
            foreach ($cart as $item) {
                try {
                    $campaign = Campaign::query()->with('sizes')->find((int) ($item['campaign_id'] ?? 0));
                    $size = $this->normalizeSize((string) ($item['size'] ?? ''));

                    if ($campaign === null) {
                        continue;
                    }

                    $this->assertCampaignPurchasable($campaign, $size);
                    $attributes = [
                        'user_id' => $user->id,
                        'campaign_id' => $campaign->id,
                        'size' => $size->value,
                    ];
                    $currentQty = (int) (CartItem::query()->where($attributes)->value('qty') ?? 0);
                    $availableQty = max(0, $this->availableQuantityFor($campaign, $size) - $currentQty);
                    $guestQty = min((int) ($item['qty'] ?? 0), $availableQty);

                    if ($guestQty > 0) {
                        CartItem::query()->updateOrCreate($attributes, ['qty' => $currentQty + $guestQty]);
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        } finally {
            session()->forget(self::GUEST_CART_KEY);
        }
    }

    private function addToDatabase(User $user, Campaign $campaign, Size $size, int $qty): CartItem
    {
        $attributes = [
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'size' => $size->value,
        ];
        $currentQty = (int) (CartItem::query()->where($attributes)->value('qty') ?? 0);
        $newQty = $currentQty + $qty;

        $this->assertAvailableQuantity($campaign, $size, $newQty);

        return CartItem::query()
            ->updateOrCreate($attributes, ['qty' => $newQty])
            ->load(['campaign', 'campaign.sizes']);
    }

    /**
     * @return Collection<int, CartItem>
     */
    private function guestItems(): Collection
    {
        $items = [];

        foreach ($this->guestCart() as $index => $item) {
            $campaign = Campaign::query()->with('sizes')->find((int) ($item['campaign_id'] ?? 0));
            $size = Size::tryFrom((string) ($item['size'] ?? ''));

            if ($campaign === null || $size === null) {
                continue;
            }

            $campaignSize = $this->findCampaignSize($campaign, $size, false);
            $items[] = $this->guestCartItem($campaign, $size, (int) ($item['qty'] ?? 0), (int) $index, $campaignSize);
        }

        return new Collection($items);
    }

    private function guestCartItem(
        Campaign $campaign,
        Size $size,
        int $qty,
        int $index,
        ?CampaignSize $campaignSize = null,
    ): CartItem {
        $campaignSize ??= $this->findCampaignSize($campaign, $size, false);
        $cartItem = new CartItem;
        $cartItem->forceFill([
            'id' => $index,
            'campaign_id' => $campaign->id,
            'size' => $size->value,
            'qty' => $qty,
        ]);
        $cartItem->setRelation('campaign', $campaign);

        return $this->hydrateCartItem($cartItem, $campaignSize);
    }

    private function hydrateCartItem(CartItem $item, ?CampaignSize $campaignSize = null): CartItem
    {
        $campaignSize ??= $this->findCampaignSize($item->campaign, $item->size, false);
        $price = $this->priceFor($item->campaign, $campaignSize);
        $item->setAttribute('price', $price);
        $item->setAttribute('unit_price', $price);
        $item->setAttribute('subtotal', $price * $item->qty);
        $item->setAttribute('slug', $item->campaign->slug);
        $item->setAttribute('title', $item->campaign->title);
        $item->setAttribute('image_url', $item->campaign->image_url);
        $item->setRelation('campaignSize', $campaignSize);

        return $item;
    }

    /**
     * @return array<int, array{campaign_id?: int, size?: string, qty?: int}>
     */
    private function guestCart(): array
    {
        $cart = session()->get(self::GUEST_CART_KEY, []);

        return is_array($cart) ? $cart : [];
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

    private function assertCampaignPurchasable(Campaign $campaign, Size $size): void
    {
        $campaignSize = $this->findCampaignSize($campaign, $size);

        if ($campaign->status === CampaignStatus::Active) {
            return;
        }

        if ($campaign->status !== CampaignStatus::SoldOut || $campaignSize->stock < 1) {
            throw new DomainException('Kampanye tidak tersedia.');
        }
    }

    private function assertAvailableQuantity(Campaign $campaign, Size $size, int $qty): void
    {
        if ($this->availableQuantityFor($campaign, $size) < $qty) {
            throw new DomainException('Stok dan kuota kampanye tidak mencukupi.');
        }
    }

    private function availableQuantityFor(Campaign $campaign, Size $size): int
    {
        $campaignSize = $this->findCampaignSize($campaign, $size);

        return max(0, $campaignSize->stock) + $this->quotaService->availableSlots($campaign);
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
