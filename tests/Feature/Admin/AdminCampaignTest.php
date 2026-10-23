<?php

namespace Tests\Feature\Admin;

use App\Enums\CampaignStatus;
use App\Enums\Size;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\CampaignSize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_view_campaign_list(): void
    {
        $this->login();
        Campaign::factory()->create();

        $this->get(route('admin.campaigns.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/campaigns/index', false)
                ->has('campaigns.data', 1),
            );
    }

    public function test_admin_can_create_campaign_with_sizes(): void
    {
        $this->login();

        $response = $this->post(route('admin.campaigns.store'), $this->campaignPayload([
            'title' => 'Koleksi Raya 2026',
            'slug' => '',
            'sizes' => $this->sizesPayload([
                'S' => ['enabled' => true, 'price_override' => 125000, 'stock' => 10],
            ]),
        ]));

        $campaign = Campaign::query()->firstOrFail();

        $response->assertRedirect(route('admin.campaigns.edit', $campaign));
        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'slug' => 'koleksi-raya-2026',
        ]);
        $this->assertDatabaseCount('campaign_sizes', 5);
        $this->assertDatabaseHas('campaign_sizes', [
            'campaign_id' => $campaign->id,
            'size' => Size::S->value,
            'price_override' => 125000,
            'enabled' => true,
        ]);
    }

    public function test_admin_can_upload_campaign_image(): void
    {
        $this->login();
        Storage::fake('public');

        $response = $this->post(route('admin.campaigns.store'), $this->campaignPayload([
            'image' => UploadedFile::fake()->image('campaign.png'),
        ]));

        $campaign = Campaign::query()->firstOrFail();
        $path = $campaign->getRawOriginal('image_url');

        $response->assertRedirect(route('admin.campaigns.edit', $campaign));
        $this->assertIsString($path);
        $this->assertStringStartsWith('campaigns/', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame(Storage::disk('public')->url($path), $campaign->image_url);
    }

    public function test_campaign_image_must_be_an_image(): void
    {
        $this->login();

        $response = $this->post(route('admin.campaigns.store'), $this->campaignPayload([
            'image' => UploadedFile::fake()->create('campaign.pdf', 100, 'application/pdf'),
        ]));

        $response->assertSessionHasErrors('image');
        $this->assertDatabaseCount('campaigns', 0);
    }

    public function test_slug_must_be_unique(): void
    {
        $this->login();
        $payload = $this->campaignPayload([
            'title' => 'Koleksi Sama',
            'slug' => '',
        ]);

        $this->post(route('admin.campaigns.store'), $payload);
        $this->post(route('admin.campaigns.store'), $payload);

        $this->assertDatabaseHas('campaigns', ['slug' => 'koleksi-sama']);
        $this->assertDatabaseHas('campaigns', ['slug' => 'koleksi-sama-2']);
    }

    public function test_admin_can_update_campaign_and_sync_sizes(): void
    {
        $this->login();
        $campaign = Campaign::factory()->create();
        CampaignSize::factory()->for($campaign)->s()->create([
            'price_override' => 100000,
            'enabled' => false,
        ]);

        $response = $this->put(route('admin.campaigns.update', $campaign), $this->campaignPayload([
            'title' => 'Koleksi Diperbarui',
            'slug' => '',
            'sizes' => $this->sizesPayload([
                'S' => ['enabled' => true, 'price_override' => 150000, 'stock' => 20],
            ]),
        ]));

        $response->assertRedirect(route('admin.campaigns.edit', $campaign));
        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'slug' => 'koleksi-diperbarui',
            'title' => 'Koleksi Diperbarui',
        ]);
        $this->assertDatabaseHas('campaign_sizes', [
            'campaign_id' => $campaign->id,
            'size' => Size::S->value,
            'price_override' => 150000,
            'enabled' => true,
            'stock' => 20,
        ]);
    }

    public function test_admin_cannot_destroy_campaign_with_sold_orders(): void
    {
        $this->login();
        $campaign = Campaign::factory()->create(['sold_count' => 1]);

        $this->from(route('admin.campaigns.index'))
            ->delete(route('admin.campaigns.destroy', $campaign))
            ->assertSessionHasErrors('sold_count')
            ->assertRedirect(route('admin.campaigns.index'));

        $this->assertDatabaseHas('campaigns', ['id' => $campaign->id]);
    }

    public function test_admin_can_destroy_empty_campaign(): void
    {
        $this->login();
        $campaign = Campaign::factory()->create(['sold_count' => 0]);

        $this->delete(route('admin.campaigns.destroy', $campaign))
            ->assertRedirect(route('admin.campaigns.index'));

        $this->assertDatabaseMissing('campaigns', ['id' => $campaign->id]);
    }

    public function test_create_form_rendered(): void
    {
        $this->login();

        $this->get(route('admin.campaigns.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/campaigns/create', false),
            );
    }

    private function login(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function campaignPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'title' => 'Kampanye Baru',
            'slug' => null,
            'description' => 'Deskripsi kampanye.',
            'base_price' => 100000,
            'quota' => 100,
            'status' => CampaignStatus::Draft->value,
            'ready_stock_enabled' => true,
            'sizes' => $this->sizesPayload(),
        ], $overrides);
    }

    /**
     * @param  array<string, array<string, mixed>>  $overrides
     * @return array<string, array<string, mixed>>
     */
    private function sizesPayload(array $overrides = []): array
    {
        $sizes = [];

        foreach (Size::cases() as $size) {
            $sizes[$size->value] = [
                'enabled' => false,
                'price_override' => null,
                'stock' => 0,
            ];
        }

        return array_replace_recursive($sizes, $overrides);
    }
}
