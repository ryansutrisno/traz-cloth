<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Admin;
use App\Models\User;
use App\Models\WaMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminCommunicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(Admin::factory()->create(['role' => AdminRole::Admin]), 'admin');
    }

    public function test_admin_can_view_communication_page(): void
    {
        $this->get(route('admin.komunikasi.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/komunikasi', false)
                ->has('buyers')
                ->has('wa_messages')
                ->where('filters.q', ''),
            );
    }

    public function test_admin_can_queue_manual_whatsapp_message(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->from(route('admin.komunikasi.index'))
            ->post(route('admin.komunikasi.send'), [
                'user_id' => $user->id,
                'message' => 'Pesan manual untuk pembeli.',
            ])
            ->assertSessionHasNoErrors();

        $message = WaMessage::query()->firstOrFail();
        $this->assertSame('manual', $message->template_key);
        $this->assertSame('Pesan manual untuk pembeli.', $message->payload['message']);
        Queue::assertPushed(SendWhatsAppMessage::class, fn (SendWhatsAppMessage $job): bool => $job->waMessageId === $message->id);
    }

    public function test_manual_message_requires_a_buyer(): void
    {
        $this->from(route('admin.komunikasi.index'))
            ->post(route('admin.komunikasi.send'), [
                'message' => 'Pesan tanpa penerima.',
            ])
            ->assertSessionHasErrors('user_id');

        $this->assertDatabaseCount('wa_messages', 0);
    }
}
