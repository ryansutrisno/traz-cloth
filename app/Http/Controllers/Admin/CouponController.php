<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CouponType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CouponStoreRequest;
use App\Http\Requests\Admin\CouponUpdateRequest;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'type' => ['nullable', Rule::enum(CouponType::class)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $search = $request->string('q')->trim()->toString();
        $type = $request->string('type')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $coupons = Coupon::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('code', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->when($type !== '', fn (Builder $query): Builder => $query->where('type', $type))
            ->when($status !== '', fn (Builder $query): Builder => $query->where('is_active', $status === 'active'))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/coupons/index', [
            'coupons' => [
                'data' => $coupons->getCollection()->map(fn (Coupon $coupon): array => [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'description' => $coupon->description,
                    'type' => $coupon->type->value,
                    'type_label' => $coupon->type->label(),
                    'value' => (float) $coupon->value,
                    'min_purchase' => $coupon->min_purchase === null ? null : (float) $coupon->min_purchase,
                    'max_discount' => $coupon->max_discount === null ? null : (float) $coupon->max_discount,
                    'used_count' => $coupon->used_count,
                    'usage_limit' => $coupon->usage_limit,
                    'per_user_limit' => $coupon->per_user_limit,
                    'starts_at' => $coupon->starts_at,
                    'expires_at' => $coupon->expires_at,
                    'is_active' => $coupon->is_active,
                    'created_at' => $coupon->created_at,
                ])->values()->all(),
                'current_page' => $coupons->currentPage(),
                'last_page' => $coupons->lastPage(),
                'per_page' => $coupons->perPage(),
                'total' => $coupons->total(),
                'links' => $coupons->linkCollection()->toArray(),
            ],
            'filters' => ['q' => $search, 'type' => $type, 'status' => $status],
            'types' => array_map(fn (CouponType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ], CouponType::cases()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/coupons/create', ['types' => $this->types()]);
    }

    public function store(CouponStoreRequest $request): RedirectResponse
    {
        $coupon = Coupon::query()->create($request->validated());

        return redirect()->route('admin.coupons.edit', $coupon)->with('status', 'Kupon berhasil dibuat.');
    }

    public function edit(Coupon $coupon): Response
    {
        return Inertia::render('admin/coupons/edit', [
            'coupon' => $coupon,
            'types' => $this->types(),
        ]);
    }

    public function update(CouponUpdateRequest $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($request->validated());

        return redirect()->route('admin.coupons.edit', $coupon)->with('status', 'Kupon berhasil diperbarui.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        if ($coupon->used_count > 0) {
            return back()->withErrors(['coupon' => 'Kupon sudah pernah dipakai dan tidak bisa dihapus.']);
        }

        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('status', 'Kupon dihapus.');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function types(): array
    {
        return array_map(fn (CouponType $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
        ], CouponType::cases());
    }
}
