<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Enums\Size;
use App\Models\Address;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\CampaignSize;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password = 'password123';

        // Admin & operator accounts.
        Admin::updateOrCreate(
            ['email' => 'admin@traz.test'],
            ['name' => 'Admin Traz', 'password' => bcrypt($password), 'role' => AdminRole::Admin->value],
        );

        Admin::updateOrCreate(
            ['email' => 'operator@traz.test'],
            ['name' => 'Operator Traz', 'password' => bcrypt($password), 'role' => AdminRole::Operator->value],
        );

        // Buyer account with verified WhatsApp number + default address.
        $buyer = User::updateOrCreate(
            ['email' => 'buyer@traz.test'],
            [
                'name' => 'Buyer Traz',
                'password' => bcrypt($password),
                'phone' => '6281234567890',
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
            ],
        );

        Address::updateOrCreate(
            ['user_id' => $buyer->id, 'label' => 'Rumah'],
            [
                'recipient_name' => 'Buyer Traz',
                'phone' => '6281234567890',
                'province' => 'DKI Jakarta',
                'city' => 'Jakarta Selatan',
                'district' => 'Kebayoran Baru',
                'subdistrict' => 'Senayan',
                'postal_code' => '12190',
                'full_address' => 'Jl. Asia Afrika No. 8, RT 01/RW 02',
                'is_default' => true,
            ],
        );

        // Active campaigns with all sizes S–XXL enabled.
        $campaigns = [
            ['title' => 'Kaos Nusantara', 'base_price' => 100000, 'quota' => 100],
            ['title' => 'Street Culture Series', 'base_price' => 125000, 'quota' => 80],
            ['title' => 'Minimalist Line', 'base_price' => 85000, 'quota' => 60],
        ];

        foreach ($campaigns as $index => $data) {
            $campaign = Campaign::updateOrCreate(
                ['slug' => str($data['title'])->slug()],
                [
                    'title' => $data['title'],
                    'description' => fake()->paragraphs(2, true),
                    'image_url' => fake()->imageUrl(640, 640, 't-shirt'),
                    'base_price' => $data['base_price'],
                    'cost_per_item' => $data['base_price'] / 2,
                    'quota' => $data['quota'],
                    'sold_count' => 0,
                    'status' => 'active',
                    'ready_stock_enabled' => true,
                ],
            );

            foreach (Size::cases() as $size) {
                CampaignSize::updateOrCreate(
                    ['campaign_id' => $campaign->id, 'size' => $size->value],
                    ['price_override' => null, 'stock' => 0, 'enabled' => true],
                );
            }
        }

        $this->command->info('Dummy data siap: admin@traz.test, operator@traz.test, buyer@traz.test (password: password123)');
    }
}
