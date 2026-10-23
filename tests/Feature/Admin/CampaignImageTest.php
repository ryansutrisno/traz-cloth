<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Campaign;
use App\Models\CampaignImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CampaignImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(Admin::factory()->create(), 'admin');
        Storage::fake('public');
    }

    public function test_admin_edit_page_receives_campaign_images(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignImage::factory()->for($campaign)->create([
            'is_primary' => true,
            'sort_order' => 1,
        ]);
        CampaignImage::factory()->for($campaign)->create([
            'sort_order' => 2,
        ]);

        $this->get(route('admin.campaigns.edit', $campaign))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/campaigns/edit')
                ->has('images', 2));
    }

    public function test_admin_can_upload_three_campaign_images_at_once(): void
    {
        $campaign = Campaign::factory()->create(['image_url' => null]);

        $this->from(route('admin.campaigns.edit', $campaign))
            ->post(route('admin.campaigns.images.store', $campaign), [
                'images' => [
                    UploadedFile::fake()->image('campaign-one.png'),
                    UploadedFile::fake()->image('campaign-two.png'),
                    UploadedFile::fake()->image('campaign-three.png'),
                ],
            ])
            ->assertRedirect(route('admin.campaigns.edit', $campaign));

        $images = $campaign->images()->orderBy('sort_order')->get();

        $this->assertCount(3, $images);
        $this->assertTrue($images->first()->is_primary);
        $this->assertSame(
            $images->first()->path,
            $campaign->fresh()->getRawOriginal('image_url'),
        );

        foreach ($images as $image) {
            Storage::disk('public')->assertExists($image->path);
        }
    }

    public function test_uploading_more_than_eight_total_images_is_rejected(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignImage::factory()->count(8)->for($campaign)->create();

        $this->from(route('admin.campaigns.edit', $campaign))
            ->post(route('admin.campaigns.images.store', $campaign), [
                'images' => [
                    UploadedFile::fake()->image('campaign-nine.png'),
                ],
            ])
            ->assertSessionHasErrors('images')
            ->assertRedirect(route('admin.campaigns.edit', $campaign));

        $this->assertCount(8, $campaign->images()->get());
        $this->assertCount(0, Storage::disk('public')->allFiles('campaigns'));
    }

    public function test_non_image_upload_is_rejected(): void
    {
        $campaign = Campaign::factory()->create();

        $this->post(route('admin.campaigns.images.store', $campaign), [
            'images' => [
                UploadedFile::fake()->create('campaign.pdf', 100, 'application/pdf'),
            ],
        ])
            ->assertSessionHasErrors('images.0');

        $this->assertDatabaseCount('campaign_images', 0);
        $this->assertCount(0, Storage::disk('public')->allFiles('campaigns'));
    }

    public function test_deleting_an_image_removes_file_and_syncs_primary_image(): void
    {
        $campaign = Campaign::factory()->create(['image_url' => null]);

        $this->post(route('admin.campaigns.images.store', $campaign), [
            'images' => [
                UploadedFile::fake()->image('campaign-one.png'),
                UploadedFile::fake()->image('campaign-two.png'),
            ],
        ]);

        $images = $campaign->images()->orderBy('sort_order')->get();
        $deleted = $images->first();
        $remaining = $images->last();

        $this->delete(route('admin.campaigns.images.destroy', [$campaign, $deleted]))
            ->assertRedirect();

        Storage::disk('public')->assertMissing($deleted->path);
        $this->assertDatabaseMissing('campaign_images', ['id' => $deleted->id]);
        $this->assertSame(
            $remaining->path,
            $campaign->fresh()->getRawOriginal('image_url'),
        );
    }

    public function test_an_image_can_be_made_primary(): void
    {
        $campaign = Campaign::factory()->create(['image_url' => null]);

        $this->post(route('admin.campaigns.images.store', $campaign), [
            'images' => [
                UploadedFile::fake()->image('campaign-one.png'),
                UploadedFile::fake()->image('campaign-two.png'),
            ],
        ]);

        $images = $campaign->images()->orderBy('sort_order')->get();
        $newPrimary = $images->last();

        $this->put(route('admin.campaigns.images.primary', [$campaign, $newPrimary]))
            ->assertRedirect();

        $this->assertSame(1, CampaignImage::query()->where('is_primary', true)->count());
        $this->assertDatabaseHas('campaign_images', [
            'id' => $newPrimary->id,
            'is_primary' => true,
        ]);
        $this->assertSame(
            $newPrimary->path,
            $campaign->fresh()->getRawOriginal('image_url'),
        );
    }

    public function test_destroying_a_campaign_removes_all_gallery_files(): void
    {
        $firstPath = 'campaigns/campaign-one.png';
        $secondPath = 'campaigns/campaign-two.png';
        Storage::disk('public')->put($firstPath, 'first');
        Storage::disk('public')->put($secondPath, 'second');

        $campaign = Campaign::factory()->create(['image_url' => $firstPath]);
        CampaignImage::factory()->for($campaign)->create([
            'path' => $firstPath,
            'is_primary' => true,
            'sort_order' => 1,
        ]);
        CampaignImage::factory()->for($campaign)->create([
            'path' => $secondPath,
            'sort_order' => 2,
        ]);

        $this->delete(route('admin.campaigns.destroy', $campaign))
            ->assertRedirect(route('admin.campaigns.index'));

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertMissing($secondPath);
        $this->assertDatabaseMissing('campaigns', ['id' => $campaign->id]);
        $this->assertDatabaseCount('campaign_images', 0);
    }
}
