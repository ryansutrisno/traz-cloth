<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\Size;
use App\Http\Controllers\Controller;
use App\Models\CartItem;
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
        $user = $request->user();
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
                $request->user(),
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

    public function update(Request $request, CartItem $cartItem): RedirectResponse
    {
        $this->ensureOwnership($request, $cartItem);
        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->cartService->updateQty($request->user(), $cartItem->id, (int) $data['qty']);
        } catch (DomainException|ValidationException $exception) {
            return back()->withErrors(['qty' => $exception->getMessage()]);
        }

        return back();
    }

    public function destroy(Request $request, CartItem $cartItem): RedirectResponse
    {
        $this->ensureOwnership($request, $cartItem);
        $this->cartService->remove($request->user(), $cartItem->id);

        return back();
    }

    private function ensureOwnership(Request $request, CartItem $cartItem): void
    {
        abort_unless($cartItem->user_id === $request->user()->id, 403);
    }
}
