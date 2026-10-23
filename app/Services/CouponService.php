<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class CouponService
{
    public function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    public function resolve(string $code, User $user, float $subtotal): Coupon
    {
        $coupon = Coupon::query()->where('code', $this->normalize($code))->first();

        if ($coupon === null) {
            throw new DomainException('Kode kupon tidak ditemukan.');
        }

        if (! $coupon->is_active) {
            throw new DomainException('Kupon sudah tidak aktif.');
        }

        if ($coupon->starts_at !== null && $coupon->starts_at->isFuture()) {
            throw new DomainException('Kupon belum bisa dipakai.');
        }

        if ($coupon->expires_at !== null && $coupon->expires_at->isPast()) {
            throw new DomainException('Kupon sudah kedaluwarsa.');
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw new DomainException('Kuota kupon sudah habis.');
        }

        if ($coupon->min_purchase !== null && $subtotal < (float) $coupon->min_purchase) {
            $minimum = number_format((float) $coupon->min_purchase, 0, ',', '.');

            throw new DomainException("Minimal belanja Rp {$minimum} untuk memakai kupon ini.");
        }

        if ($this->hasReachedUserLimit($coupon, $user)) {
            throw new DomainException('Kamu sudah memakai kupon ini.');
        }

        return $coupon;
    }

    public function discountFor(Coupon $coupon, float $subtotal): float
    {
        $discount = match ($coupon->type) {
            CouponType::Percentage => min(
                $coupon->max_discount === null ? PHP_FLOAT_MAX : (float) $coupon->max_discount,
                $subtotal * (float) $coupon->value / 100,
            ),
            CouponType::Fixed => (float) $coupon->value,
        };

        return max(0, round(min($subtotal, $discount)));
    }

    public function hasReachedUserLimit(Coupon $coupon, User $user): bool
    {
        $limit = $coupon->per_user_limit ?? 1;

        return Order::query()
            ->where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->whereNotIn('status', [OrderStatus::DibatalkanBuyer->value, OrderStatus::DibatalkanSistem->value])
            ->count() >= $limit;
    }

    public function consume(Coupon $coupon): Coupon
    {
        return DB::transaction(function () use ($coupon): Coupon {
            $lockedCoupon = Coupon::query()->lockForUpdate()->findOrFail($coupon->id);

            if ($lockedCoupon->usage_limit !== null && $lockedCoupon->used_count >= $lockedCoupon->usage_limit) {
                throw new DomainException('Kuota kupon sudah habis.');
            }

            $lockedCoupon->increment('used_count');

            return $lockedCoupon->refresh();
        });
    }

    public function releaseUsage(Order $order): void
    {
        if ($order->coupon_id === null) {
            return;
        }

        Coupon::query()
            ->whereKey($order->coupon_id)
            ->where('used_count', '>', 0)
            ->decrement('used_count');
    }
}
