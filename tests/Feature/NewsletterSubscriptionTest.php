<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NewsletterSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config()->set('inertia.testing.ensure_pages_exist', false);
    }

    public function test_guest_can_subscribe_newsletter(): void
    {
        $this->post(route('newsletter.subscribe'), ['email' => 'subscriber@example.com'])
            ->assertRedirect();

        $this->assertDatabaseHas('newsletter_subscriptions', [
            'email' => 'subscriber@example.com',
        ]);
    }

    public function test_duplicate_email_rejected(): void
    {
        $this->post(route('newsletter.subscribe'), ['email' => 'subscriber@example.com']);

        $this->from(route('home'))
            ->post(route('newsletter.subscribe'), ['email' => 'subscriber@example.com'])
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('newsletter_subscriptions', 1);
    }

    public function test_invalid_email_rejected(): void
    {
        $this->from(route('home'))
            ->post(route('newsletter.subscribe'), ['email' => 'bukan-email'])
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('newsletter_subscriptions', 0);
    }

    public function test_static_pages_render(): void
    {
        $this->get(route('terms'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('terms'));

        $this->get(route('privacy-policy'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('privacy'));

        $this->get(route('contact'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('contact'));

    }
}
