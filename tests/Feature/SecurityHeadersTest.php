<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_page_includes_security_headers(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $this->assertBasicHeaders($response);
    }

    public function test_admin_login_page_includes_security_headers(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertOk();
        $this->assertBasicHeaders($response);
    }

    public function test_hsts_is_not_added_to_http_requests_in_testing(): void
    {
        $this->get(route('home'))
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_is_added_to_secure_requests_in_production(): void
    {
        $originalEnvironment = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $response = (new SecurityHeaders)->handle(
                Request::create('https://example.test'),
                static fn (Request $request): Response => new Response('OK'),
            );

            $this->assertSame(
                'max-age=31536000; includeSubDomains',
                $response->headers->get('Strict-Transport-Security'),
            );
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
    }

    public function test_https_scheme_is_not_forced_in_testing(): void
    {
        $this->assertSame('http', parse_url(route('home'), PHP_URL_SCHEME));
    }

    private function assertBasicHeaders(TestResponse $response): void
    {
        $response
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    }
}
