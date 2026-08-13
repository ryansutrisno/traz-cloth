<?php

namespace Tests\Feature\Auth;

use App\Models\OtpCode;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private object $whatsapp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->whatsapp = new class implements WhatsAppService
        {
            /** @var array<int, array{phone: string, message: string}> */
            public array $sent = [];

            public function send(string $phone, string $message): void
            {
                $this->sent[] = [
                    'phone' => $phone,
                    'message' => $message,
                ];
            }
        };

        $this->app->instance(WhatsAppService::class, $this->whatsapp);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_registration_sends_otp_and_redirects_to_verification(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '081234567890',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('verify-otp'));
        $this->assertCount(1, $this->whatsapp->sent);
        $response->assertSessionHas('registration_pending.phone', '6281234567890');
        $this->assertDatabaseHas('otp_codes', [
            'phone' => '6281234567890',
            'purpose' => 'registration',
        ]);
        $this->assertGuest();
    }

    public function test_user_can_verify_otp_and_login(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '081234567890',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $code = $this->otpFromMessage($this->whatsapp->sent[0]['message']);

        $response = $this->post(route('verify-otp.check'), ['otp' => $code]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'phone' => '6281234567890',
        ]);
        $this->assertNotNull(
            User::query()->where('email', 'test@example.com')->firstOrFail()->phone_verified_at,
        );
        $response->assertSessionMissing('registration_pending');
    }

    public function test_otp_invalid_rejects(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '081234567890',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response = $this->post(route('verify-otp.check'), ['otp' => '000000']);

        $response->assertSessionHasErrors('otp');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['phone' => '6281234567890']);
    }

    public function test_verify_otp_requires_pending_registration(): void
    {
        $response = $this->get(route('verify-otp'));

        $response->assertRedirect(route('register'));
    }

    public function test_otp_expired_rejects(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '081234567890',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $code = $this->otpFromMessage($this->whatsapp->sent[0]['message']);
        OtpCode::query()
            ->where('phone', '6281234567890')
            ->update(['expires_at' => now()->subMinute()]);

        $response = $this->post(route('verify-otp.check'), ['otp' => $code]);

        $response->assertSessionHasErrors('otp');
    }

    public function test_resend_otp_respects_cooldown(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '081234567890',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->post(route('verify-otp.resend'), ['phone' => '0812****7890'])
            ->assertSessionHasErrors('otp');
        $this->assertCount(1, $this->whatsapp->sent);

        $this->travel(61)->seconds();

        $response = $this->post(route('verify-otp.resend'), ['phone' => '0812****7890']);

        $response->assertSessionDoesntHaveErrors('otp');
        $this->assertCount(2, $this->whatsapp->sent);
        $response->assertSessionHas('status');
    }

    private function otpFromMessage(string $message): string
    {
        preg_match('/(\d{6})/', $message, $matches);

        return $matches[1] ?? '';
    }
}
