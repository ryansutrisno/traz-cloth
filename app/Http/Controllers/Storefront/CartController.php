<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\Size;
use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\User;
use App\Services\CartService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(private CartService $cartService) {}

    public function index(Request $request): Response
    {
        $user = $this->cartUser($request);
        $items = $this->cartService->itemsFor($user);
        $total = $items->sum(fn (CartItem $item): int => (int) $item->getAttribute('subtotal'));

        return Inertia::render('cart/index', [
            'items' => $items,
            'total' => $total,
            'empty' => $items->isEmpty(),
        ]);
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'size' => ['required', Rule::enum(Size::class)],
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->cartService->add(
                $this->cartUser($request),
                (int) $data['campaign_id'],
                $data['size'],
                (int) $data['qty'],
            );
        } catch (DomainException|ValidationException $exception) {
            $errorKey = str_contains(strtolower($exception->getMessage()), 'ukuran')
                ? 'size'
                : 'qty';

            return back()->withErrors([$errorKey => $exception->getMessage()]);
        }

        return back();
    }

    public function update(Request $request, int $cartItem): RedirectResponse
    {
        $this->ensureOwnership($request, $cartItem);
        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->cartService->updateQty($this->cartUser($request), $cartItem, (int) $data['qty']);
        } catch (DomainException|ValidationException $exception) {
            return back()->withErrors(['qty' => $exception->getMessage()]);
        }

        return back();
    }

    public function destroy(Request $request, int $cartItem): RedirectResponse
    {
        $this->ensureOwnership($request, $cartItem);
        $this->cartService->remove($this->cartUser($request), $cartItem);

        return back();
    }

    private function ensureOwnership(Request $request, int $cartItemId): void
    {
        $user = $this->cartUser($request);

        if ($user === null) {
            return;
        }

        $cartItem = CartItem::query()->findOrFail($cartItemId);
        abort_unless($cartItem->user_id === $user->id, 403);
    }

    private function cartUser(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
