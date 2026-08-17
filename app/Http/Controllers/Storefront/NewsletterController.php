<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(Request $request): RedirectResponse
    {
        $data = $request->validate(
            ['email' => ['required', 'email', 'max:255', 'unique:newsletter_subscriptions,email']],
            ['email.unique' => 'Email sudah terdaftar.'],
        );

        NewsletterSubscription::create(['email' => $data['email']]);

        return redirect()->back()->with('status', 'Terima kasih sudah berlangganan.');
    }
}
