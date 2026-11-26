<?php

namespace ItmoSoft\Pages\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use ItmoSoft\Pages\Models\Page;
use ItmoSoft\Pages\Models\PageRevision;
use InvalidArgumentException;

class PageManager
{
    private const SNAPSHOT_FIELDS = [
        'title', 'slug', 'type', 'template', 'content', 'content_blocks', 'metadata',
        'status', 'show_in_navigation', 'navigation_location', 'navigation_order',
        'published_at', 'scheduled_at',
    ];

    public function paginate(?int $storeId, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $limit = max(1, min($perPage ?? (int) config('itmosoft-pages.default_per_page', 20), (int) config('itmosoft-pages.max_per_page', 100)));

        return Page::query()->forStore($storeId)
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['type'] ?? null, fn ($q, $value) => $q->where('type', $value))
            ->when($filters['search'] ?? null, fn ($q, $value) => $q->where(fn ($inner) => $inner->where('title', 'like', '%'.$value.'%')->orWhere('slug', 'like', '%'.$value.'%')))
            ->orderByDesc('updated_at')->paginate($limit);
    }

    public function create(?int $storeId, array $attributes, ?int $actorId = null): Page
    {
        return DB::transaction(function () use ($storeId, $attributes, $actorId): Page {
            $page = new Page();
            $page->fill($this->validatedAttributes($attributes));
            $page->forceFill(['store_id' => $storeId, 'created_by' => $actorId, 'updated_by' => $actorId]);
            $page->save();
            $this->recordRevision($page, $actorId);
            return $page->refresh();
        });
    }

    public function update(int $pageId, ?int $storeId, array $attributes, ?int $actorId = null, ?int $expectedVersion = null): Page
    {
        return DB::transaction(function () use ($pageId, $storeId, $attributes, $actorId, $expectedVersion): Page {
            $page = Page::query()->forStore($storeId)->lockForUpdate()->find($pageId);
            if (! $page) {
                throw (new ModelNotFoundException())->setModel(Page::class, [$pageId]);
            }
            if ($expectedVersion !== null && (int) $page->revisions()->max('version') !== $expectedVersion) {
                throw ValidationException::withMessages(['version' => 'This page has changed since you opened it. Reload before saving.']);
            }
            if ($page->is_system && array_key_exists('slug', $attributes) && $attributes['slug'] !== $page->slug) {
                throw ValidationException::withMessages(['slug' => 'The slug of a system page cannot be changed through this operation.']);
            }
            $page->fill($this->validatedAttributes($attributes));
            $page->updated_by = $actorId;
            $page->save();
            $this->recordRevision($page, $actorId);
            return $page->refresh();
        });
    }

    public function publish(int $pageId, ?int $storeId, ?int $actorId = null): Page
    {
        return $this->update($pageId, $storeId, ['status' => Page::STATUS_PUBLISHED, 'published_at' => now(), 'scheduled_at' => null], $actorId);
    }

    public function unpublish(int $pageId, ?int $storeId, ?int $actorId = null): Page
    {
        return $this->update($pageId, $storeId, ['status' => Page::STATUS_DRAFT, 'published_at' => null, 'scheduled_at' => null], $actorId);
    }

    public function archive(int $pageId, ?int $storeId, ?int $actorId = null): Page
    {
        return $this->update($pageId, $storeId, ['status' => Page::STATUS_ARCHIVED, 'scheduled_at' => null], $actorId);
    }

    public function delete(int $pageId, ?int $storeId): void
    {
        DB::transaction(function () use ($pageId, $storeId): void {
            $page = Page::query()->forStore($storeId)->lockForUpdate()->find($pageId);
            if (! $page) {
                throw (new ModelNotFoundException())->setModel(Page::class, [$pageId]);
            }
            if ($page->is_system) {
                throw ValidationException::withMessages(['page' => 'System pages cannot be deleted.']);
            }
            $page->delete();
        });
    }

    public function findPublishedBySlug(string $slug, ?int $storeId): ?Page
    {
        return Page::query()->forStore($storeId)->published()->where('slug', $slug)->first();
    }

    public function navigation(string $location, ?int $storeId): array
    {
        if (! in_array($location, config('itmosoft-pages.navigation_locations', []), true)) {
            throw new InvalidArgumentException('Unsupported navigation location.');
        }
        return Page::query()->forStore($storeId)->published()->inNavigation($location)
            ->get(['id', 'title', 'slug', 'type', 'template', 'metadata', 'navigation_order'])->all();
    }

    public function restoreRevision(int $pageId, int $revisionId, ?int $storeId, ?int $actorId = null): Page
    {
        return DB::transaction(function () use ($pageId, $revisionId, $storeId, $actorId): Page {
            $page = Page::query()->forStore($storeId)->lockForUpdate()->find($pageId);
            if (! $page) {
                throw (new ModelNotFoundException())->setModel(Page::class, [$pageId]);
            }
            $revision = $page->revisions()->find($revisionId);
            if (! $revision) {
                throw (new ModelNotFoundException())->setModel(PageRevision::class, [$revisionId]);
            }
            if ($page->is_system && ($revision->snapshot['slug'] ?? null) !== $page->slug) {
                throw ValidationException::withMessages(['revision' => 'This revision cannot change a system page slug.']);
            }
            $page->fill($this->validatedAttributes($revision->snapshot));
            $page->updated_by = $actorId;
            $page->save();
            $this->recordRevision($page, $actorId);
            return $page->refresh();
        });
    }

    private function validatedAttributes(array $attributes): array
    {
        $allowed = Arr::only($attributes, self::SNAPSHOT_FIELDS);
        if (array_key_exists('content_blocks', $allowed) && $allowed['content_blocks'] !== null && ! is_array($allowed['content_blocks'])) {
            throw ValidationException::withMessages(['content_blocks' => 'Content blocks must be an array.']);
        }
        if (array_key_exists('metadata', $allowed) && $allowed['metadata'] !== null && ! is_array($allowed['metadata'])) {
            throw ValidationException::withMessages(['metadata' => 'Metadata must be an array.']);
        }
        return $allowed;
    }

    private function recordRevision(Page $page, ?int $actorId): void
    {
        $version = (int) $page->revisions()->max('version') + 1;
        $snapshot = Arr::only($page->getAttributes(), self::SNAPSHOT_FIELDS);
        foreach (['content_blocks', 'metadata'] as $field) {
            if (isset($snapshot[$field]) && is_string($snapshot[$field])) {
                $snapshot[$field] = json_decode($snapshot[$field], true, 512, JSON_THROW_ON_ERROR);
            }
        }
        $page->revisions()->create(['version' => $version, 'snapshot' => $snapshot, 'created_by' => $actorId]);
    }
}
