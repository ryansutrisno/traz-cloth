<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\OtpService;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request, OtpService $otpService): Response
    {
        $user = $this->authenticatedUser($request);
        $orders = Order::query()->where('user_id', $user->id);
        $pendingPhone = $request->session()->get('account_phone_pending');
        $pendingPhone = is_string($pendingPhone) ? $pendingPhone : null;

        return Inertia::render('account/index', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'phone_verified' => $user->phone_verified_at !== null,
            ],
            'statistics' => [
                'total' => (clone $orders)->count(),
                'in_progress' => (clone $orders)->whereNotIn('status', [
                    OrderStatus::Delivered->value,
                    OrderStatus::DibatalkanBuyer->value,
                    OrderStatus::DibatalkanSistem->value,
                ])->count(),
                'completed' => (clone $orders)->where('status', OrderStatus::Delivered->value)->count(),
            ],
            'default_address' => $user->addresses()
                ->where('is_default', true)
                ->first()
                ?->only([
                    'id',
                    'label',
                    'recipient_name',
                    'phone',
                    'full_address',
                    'city',
                    'province',
                    'postal_code',
                ]),
            'phone_verification' => [
                'pending' => $pendingPhone !== null,
                'phone' => $pendingPhone === null ? null : $this->maskedPhone($pendingPhone),
                'resend_cooldown' => $pendingPhone === null
                    ? 0
                    : $otpService->resendCooldownSeconds($pendingPhone, 'phone_change'),
            ],
        ]);
    }

    public function updatePhone(Request $request, OtpService $otpService, WhatsAppService $whatsAppService): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);
        $phone = $this->normalizePhone($validated['phone']);

        Validator::make(
            ['phone' => $phone],
            [
                'phone' => [
                    'required',
                    'digits_between:10,15',
                    Rule::unique('users', 'phone')->ignore($user->id),
                ],
            ],
        )->validate();

        $pendingPhone = $request->session()->get('account_phone_pending');
        if ($pendingPhone === $phone && $otpService->resendCooldownSeconds($phone, 'phone_change') > 0) {
            return back()->withErrors(['phone' => 'Tunggu 60 detik sebelum mengirim ulang kode OTP.']);
        }

        $request->session()->put('account_phone_pending', $phone);
        $code = $otpService->generate($phone, 'phone_change');
        $whatsAppService->send(
            $phone,
            'Kode verifikasi perubahan nomor Traz Cloth: '.$code.'. Berlaku 5 menit. Jangan bagikan ke siapa pun.',
        );

        return back()->with('status', 'Kode OTP sudah dikirim ke nomor baru.');
    }

    public function verifyPhone(Request $request, OtpService $otpService): RedirectResponse
    {
        $pendingPhone = $request->session()->get('account_phone_pending');

        if (! is_string($pendingPhone) || $pendingPhone === '') {
            return back()->withErrors(['otp' => 'Silakan minta kode OTP terlebih dahulu.']);
        }

        $validated = $request->validate([
            'otp' => ['required', 'string', 'digits:6'],
        ]);

        if (! $otpService->verify($pendingPhone, $validated['otp'], 'phone_change')) {
            return back()->withErrors(['otp' => 'Kode OTP salah atau sudah kedaluwarsa.'])->withInput();
        }

        $user = $this->authenticatedUser($request);
        $user->update([
            'phone' => $pendingPhone,
            'phone_verified_at' => now(),
        ]);
        $request->session()->forget('account_phone_pending');

        return back()->with('status', 'Nomor WhatsApp berhasil diverifikasi.');
    }

    private function normalizePhone(string $phone): string
    {
        $normalizedPhone = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($normalizedPhone, '0')) {
            return '62'.substr($normalizedPhone, 1);
        }

        return $normalizedPhone;
    }

    private function maskedPhone(string $phone): string
    {
        $localPhone = str_starts_with($phone, '62') ? '0'.substr($phone, 2) : $phone;

        return substr($localPhone, 0, 4).'****'.substr($localPhone, -4);
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
