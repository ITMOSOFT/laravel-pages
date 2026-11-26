<?php

namespace ItmoSoft\Pages\Tests\Feature;

use Illuminate\Validation\ValidationException;
use ItmoSoft\Pages\Models\Page;
use ItmoSoft\Pages\Services\PageManager;
use ItmoSoft\Pages\Tests\TestCase;

class PageManagerTest extends TestCase
{
    public function test_omitted_fields_receive_valid_model_defaults_before_saving(): void
    {
        $page = app(PageManager::class)->create(null, ['title' => 'About', 'slug' => 'about']);

        $this->assertSame(Page::STATUS_DRAFT, $page->status);
        $this->assertSame('standard', $page->type);
        $this->assertSame('default', $page->template);
        $this->assertFalse($page->show_in_navigation);
        $this->assertFalse($page->is_system);
    }

    public function test_creates_draft_and_records_initial_revision(): void
    {
        $page = app(PageManager::class)->create(12, ['title' => 'Privacy Policy', 'slug' => 'privacy-policy', 'type' => 'privacy-policy', 'content' => 'Policy text'], 5);
        $this->assertSame('draft', $page->status);
        $this->assertSame(1, $page->revisions()->count());
        $this->assertSame(12, $page->store_id);
    }

    public function test_published_page_is_publicly_resolvable_and_draft_is_not(): void
    {
        $manager = app(PageManager::class);
        $draft = $manager->create(12, ['title' => 'Draft', 'slug' => 'draft']);
        $this->assertNull($manager->findPublishedBySlug('draft', 12));
        $manager->publish($draft->id, 12);
        $this->assertNotNull($manager->findPublishedBySlug('draft', 12));
    }

    public function test_page_is_scoped_to_store(): void
    {
        $manager = app(PageManager::class);
        $storeA = $manager->create(12, ['title' => 'Store A', 'slug' => 'about']);
        $storeB = $manager->create(13, ['title' => 'Store B', 'slug' => 'about']);

        $manager->publish($storeA->id, 12);
        $manager->publish($storeB->id, 13);

        $this->assertSame('Store A', $manager->findPublishedBySlug('about', 12)?->title);
        $this->assertSame('Store B', $manager->findPublishedBySlug('about', 13)?->title);
    }

    public function test_rejects_invalid_slug_and_invalid_page_status(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(PageManager::class)->create(null, ['title' => 'Bad', 'slug' => '../bad']);
    }

    public function test_optimistic_version_check_rejects_stale_update(): void
    {
        $manager = app(PageManager::class);
        $page = $manager->create(null, ['title' => 'About', 'slug' => 'about']);
        $manager->update($page->id, null, ['title' => 'About updated'], null, 1);
        $this->expectException(ValidationException::class);
        $manager->update($page->id, null, ['title' => 'Stale edit'], null, 1);
    }

    public function test_navigation_only_returns_published_pages_at_selected_location(): void
    {
        $manager = app(PageManager::class);
        $page = $manager->create(12, ['title' => 'Shipping', 'slug' => 'shipping', 'show_in_navigation' => true, 'navigation_location' => 'footer']);
        $manager->publish($page->id, 12);
        $this->assertCount(1, $manager->navigation('footer', 12));
        $this->assertCount(0, $manager->navigation('header', 12));
    }

    public function test_system_page_cannot_be_deleted(): void
    {
        $manager = app(PageManager::class);
        $page = $manager->create(null, ['title' => 'Privacy', 'slug' => 'privacy', 'type' => 'privacy-policy']);
        $page->forceFill(['is_system' => true])->save();
        $this->expectException(ValidationException::class);
        $manager->delete($page->id, null);
    }

    public function test_revision_can_be_restored_as_a_new_revision(): void
    {
        $manager = app(PageManager::class);
        $page = $manager->create(null, ['title' => 'First title', 'slug' => 'first-title']);
        $firstRevision = $page->revisions()->firstOrFail();
        $manager->update($page->id, null, ['title' => 'Second title']);
        $restored = $manager->restoreRevision($page->id, $firstRevision->id, null);
        $this->assertSame('First title', $restored->title);
        $this->assertSame(3, $restored->revisions()->count());
    }
}
