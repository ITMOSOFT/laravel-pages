<?php

namespace ItmoSoft\Pages\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;
use JsonException;

class Page extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    /**
     * Defaults must exist on the model before the saving event runs. Database
     * defaults are applied only after INSERT and therefore cannot satisfy
     * validation performed by the model's saving hook.
     */
    protected $attributes = [
        'type' => 'standard',
        'template' => 'default',
        'status' => self::STATUS_DRAFT,
        'is_system' => false,
        'show_in_navigation' => false,
        'navigation_order' => 0,
    ];

    protected $guarded = [];

    public function getTable(): string
    {
        return (string) config('itmosoft-pages.pages_table', 'pages');
    }

    protected function casts(): array
    {
        return [
            'content_blocks' => 'array',
            'metadata' => 'array',
            'is_system' => 'boolean',
            'show_in_navigation' => 'boolean',
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $page): void {
            $maxTitle = (int) config('itmosoft-pages.title_max_length', 255);
            $maxSlug = (int) config('itmosoft-pages.slug_max_length', 180);
            $maxContent = (int) config('itmosoft-pages.content_max_length', 200000);

            if (! is_string($page->title) || trim($page->title) === '' || mb_strlen($page->title) > $maxTitle) {
                throw new InvalidArgumentException("Page title is required and cannot exceed {$maxTitle} characters.");
            }

            if (! is_string($page->slug) || mb_strlen($page->slug) > $maxSlug || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $page->slug) !== 1) {
                throw new InvalidArgumentException('Page slug must contain lowercase letters, numbers, and single hyphens only.');
            }

            if (! in_array($page->status, [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED], true)) {
                throw new InvalidArgumentException('Invalid page status.');
            }

            $types = config('itmosoft-pages.page_types', ['standard']);
            if (! is_string($page->type) || ! in_array($page->type, $types, true)) {
                throw new InvalidArgumentException('Invalid page type.');
            }

            if (! is_string($page->template) || preg_match('/^[a-z0-9_-]{1,100}$/', $page->template) !== 1) {
                throw new InvalidArgumentException('Invalid page template identifier.');
            }

            if ($page->content !== null && (! is_string($page->content) || mb_strlen($page->content) > $maxContent)) {
                throw new InvalidArgumentException('Page content must be text and cannot exceed the configured limit.');
            }

            foreach (['content_blocks', 'metadata'] as $attribute) {
                $value = $page->{$attribute};
                if ($value !== null && ! is_array($value)) {
                    throw new InvalidArgumentException("Page {$attribute} must be an array or null.");
                }
                if ($value !== null) {
                    try {
                        json_encode($value, JSON_THROW_ON_ERROR);
                    } catch (JsonException $exception) {
                        throw new InvalidArgumentException("Page {$attribute} must be JSON encodable.", previous: $exception);
                    }
                }
            }

            if ($page->show_in_navigation) {
                $locations = config('itmosoft-pages.navigation_locations', []);
                if (! is_string($page->navigation_location) || ! in_array($page->navigation_location, $locations, true)) {
                    throw new InvalidArgumentException('A valid navigation location is required for pages shown in navigation.');
                }
            }

            if ($page->status === self::STATUS_PUBLISHED && $page->published_at === null) {
                $page->published_at = now();
            }
        });
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class, 'page_id');
    }

    public function scopeForStore(Builder $query, ?int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(function (Builder $query): void {
                $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now());
            });
    }

    public function scopeInNavigation(Builder $query, string $location): Builder
    {
        return $query->where('show_in_navigation', true)
            ->where('navigation_location', $location)
            ->orderBy('navigation_order')
            ->orderBy('title');
    }
}
