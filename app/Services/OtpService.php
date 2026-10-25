<?php

namespace App\Services;

use App\Models\OtpCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    public function generate(string $phone, string $purpose = 'registration'): string
    {
        OtpCode::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::query()->create([
            'phone' => $phone,
            'purpose' => $purpose,
            'code' => Hash::make($code),
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        return $code;
    }

    public function verify(string $phone, string $code, string $purpose = 'registration'): bool
    {
        $otp = OtpCode::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();

        if ($otp === null || $otp->attempts >= 5) {
            return false;
        }

        if (! Hash::check($code, $otp->code)) {
            $otp->increment('attempts');

            return false;
        }

        $otp->delete();

        return true;
    }

    public function resendCooldownSeconds(string $phone, string $purpose = 'registration'): int
    {
        $otp = OtpCode::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->latest()
            ->first();

        if ($otp === null) {
            return 0;
        }

        return (int) max(0, 60 - Carbon::now()->diffInSeconds($otp->created_at, true));
    }
}
