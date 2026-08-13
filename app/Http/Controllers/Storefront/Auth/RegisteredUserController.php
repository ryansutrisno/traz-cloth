<?php

namespace App\Http\Controllers\Storefront\Auth;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    use PasswordValidationRules;

    public function showRegister(): Response
    {
        return Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'lowercase', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => $this->passwordRules(),
        ]);

        $phone = $this->normalizePhone($validated['phone']);

        Validator::make(
            ['phone' => $phone],
            ['phone' => ['required', 'unique:users,phone']],
        )->validate();

        session()->put('registration_pending', [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $phone,
            'password' => Hash::make($validated['password']),
        ]);

        $this->sendOtp($phone);

        return redirect()->route('verify-otp');
    }

    public function showVerifyOtp(): Response|RedirectResponse
    {
        $pending = session()->get('registration_pending');

        if (! is_array($pending) || ! isset($pending['phone'])) {
            return redirect()->route('register');
        }

        $phone = (string) $pending['phone'];

        return Inertia::render('auth/verify-otp', [
            'phone' => $this->maskedPhone($phone),
            'resendAfter' => app(OtpService::class)->resendCooldownSeconds($phone, 'registration'),
        ]);
    }

    public function check(Request $request): RedirectResponse
    {
        $pending = session()->get('registration_pending');

        if (! is_array($pending) || ! isset($pending['phone'])) {
            return redirect()->route('register');
        }

        $validated = $request->validate([
            'otp' => ['required', 'string', 'digits:6'],
        ]);
        $phone = (string) $pending['phone'];

        if (! app(OtpService::class)->verify($phone, $validated['otp'], 'registration')) {
            return back()
                ->withErrors(['otp' => 'Kode OTP salah atau sudah kedaluwarsa.'])
                ->withInput();
        }

        $user = User::create([
            'name' => $pending['name'],
            'email' => $pending['email'],
            'phone' => $phone,
            'password' => $pending['password'],
            'phone_verified_at' => now(),
        ]);

        Auth::login($user);
        session()->forget('registration_pending');

        return redirect()->route('dashboard');
    }

    public function resend(Request $request): RedirectResponse
    {
        $pending = session()->get('registration_pending');

        if (! is_array($pending)) {
            return redirect()->route('register');
        }

        $phone = isset($pending['phone'])
            ? (string) $pending['phone']
            : (string) $request->input('phone', '');

        if ($phone === '') {
            return redirect()->route('register');
        }

        $cooldown = app(OtpService::class)->resendCooldownSeconds($phone, 'registration');

        if ($cooldown > 0) {
            return back()->withErrors(['otp' => 'Tunggu sebentar sebelum mengirim ulang.']);
        }

        $this->sendOtp($phone);
        session()->flash('status', 'Kode baru sudah dikirim.');

        return back();
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
        $localPhone = '0'.substr($phone, 2);

        return substr($localPhone, 0, 4).'****'.substr($localPhone, -4);
    }

    private function sendOtp(string $phone): void
    {
        $code = app(OtpService::class)->generate($phone, 'registration');

        app(WhatsAppService::class)->send(
            $phone,
            'Kode verifikasi Traz Cloth: '.$code.'. Berlaku 5 menit. Jangan bagikan ke siapa pun.',
        );
    }
}
