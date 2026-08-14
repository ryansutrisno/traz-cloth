<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AddressController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('q')->trim()->toString();
        $addresses = $request->user()->addresses()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('label', 'like', '%'.$search.'%')
                        ->orWhere('recipient_name', 'like', '%'.$search.'%')
                        ->orWhere('city', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get([
                'id',
                'label',
                'recipient_name',
                'phone',
                'province',
                'city',
                'district',
                'subdistrict',
                'postal_code',
                'full_address',
                'is_default',
            ]);

        return Inertia::render('addresses/index', [
            'addresses' => $addresses,
            'filters' => ['q' => $search],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $user = $request->user();

        DB::transaction(function () use ($data, $user): void {
            $isFirst = ! $user->addresses()->exists();
            $isDefault = $isFirst || (bool) ($data['is_default'] ?? false);

            if ($isDefault) {
                $this->clearDefaults($user);
            }

            Address::query()->create([
                ...$data,
                'user_id' => $user->id,
                'is_default' => $isDefault,
            ]);
        });

        return redirect()->route('addresses.index')->with('status', 'Alamat disimpan.');
    }

    public function update(Request $request, Address $address): RedirectResponse
    {
        $this->ensureOwnership($request, $address);
        $data = $this->validated($request);
        $user = $request->user();

        DB::transaction(function () use ($data, $user, $address): void {
            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault) {
                $this->clearDefaults($user, $address->id);
            } elseif ($address->is_default && $user->addresses()->whereKeyNot($address->id)->where('is_default', true)->doesntExist()) {
                $isDefault = true;
            }

            $address->update([
                ...$data,
                'is_default' => $isDefault,
            ]);
        });

        return back()->with('status', 'Alamat diperbarui.');
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        $this->ensureOwnership($request, $address);

        DB::transaction(function () use ($request, $address): void {
            if ($address->is_default) {
                $replacement = $request->user()->addresses()
                    ->whereKeyNot($address->id)
                    ->latest('updated_at')
                    ->first();

                $replacement?->update(['is_default' => true]);
            }

            $address->delete();
        });

        return back()->with('status', 'Alamat dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'subdistrict' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:10'],
            'full_address' => ['required', 'string', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }

    private function ensureOwnership(Request $request, Address $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 403);
    }

    private function clearDefaults(User $user, ?int $exceptId = null): void
    {
        $user->addresses()
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->update(['is_default' => false]);
    }
}
