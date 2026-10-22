<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontStaticPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_static_storefront_pages_render(): void
    {
        $pages = [
            ['faq', 'faq'],
            ['about', 'about'],
            ['shipping-policy', 'shipping-policy'],
        ];

        foreach ($pages as [$route, $component]) {
            $this->get(route($route))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component($component));
        }
    }
}
