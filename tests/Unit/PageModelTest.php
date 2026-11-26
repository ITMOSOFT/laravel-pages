<?php

namespace ItmoSoft\Pages\Tests\Unit;

use ItmoSoft\Pages\Models\Page;
use ItmoSoft\Pages\Tests\TestCase;

class PageModelTest extends TestCase
{
    public function test_published_page_gets_published_timestamp(): void
    {
        $page = new Page(['title' => 'About', 'slug' => 'about', 'status' => 'published']);
        $page->save();
        $this->assertNotNull($page->published_at);
    }

    public function test_navigation_page_requires_valid_location(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $page = new Page(['title' => 'About', 'slug' => 'about', 'show_in_navigation' => true, 'navigation_location' => 'random']);
        $page->save();
    }
}
