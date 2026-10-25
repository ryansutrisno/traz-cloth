<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WaMessageStatus;
use App\Jobs\SendWhatsAppMessage;
use App\Models\User;
use App\Models\WaMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommunicationController
{
    public function index(Request $request): Response
    {
        $search = $request->string('q')->trim()->toString();
        $buyers = User::query()
            ->select(['id', 'name', 'email', 'phone'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();
        $messages = WaMessage::query()
            ->latest()
            ->limit(20)
            ->get(['id', 'phone', 'template_key', 'status', 'sent_at', 'payload']);

        return Inertia::render('admin/komunikasi', [
            'buyers' => $buyers,
            'wa_messages' => $messages,
            'filters' => ['q' => $search],
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'message' => ['required', 'string', 'max:1000'],
        ]);
        $user = User::query()->whereKey($data['user_id'])->firstOrFail();
        $waMessage = WaMessage::query()->create([
            'order_id' => null,
            'phone' => (string) $user->phone,
            'template_key' => 'manual',
            'payload' => ['message' => (string) $data['message']],
            'status' => WaMessageStatus::Queued,
        ]);

        SendWhatsAppMessage::dispatch($waMessage->id);

        return back()->with('status', 'Pesan dikirim ke antrean.');
    }
}
