# ITMOSOFT Laravel Pages

Reusable multi-store static page and content management for Laravel 12 and 13. Intended for a Nuxt storefront and a custom Livewire admin, while keeping content persistence and publication rules in an independent Composer package.

## Features

- Store-scoped pages (nullable `store_id` supports a single/global store installation).
- Draft, published, and archived lifecycle; scheduled visibility; soft deletion.
- Safe slug/title/template/type validation and configurable page types.
- Rich text content plus structured JSON content blocks.
- Metadata storage for page-level SEO/social values. Integrate `itmosoft/laravel-seo` for rendering metadata.
- Publication and navigation queries (header, footer, legal, help by default).
- Revision snapshots, restore, and optimistic version check to guard against stale admin edits.
- Public JSON endpoints for published content and navigation.
- Configurable table names and store model/table integration.

## Installation

```bash
composer require itmosoft/laravel-pages
php artisan vendor:publish --tag=itmosoft-pages-config
php artisan vendor:publish --tag=itmosoft-pages-migrations
php artisan migrate
```

The `pages.store_id` column intentionally has no database foreign key by default, so the package does not hard-couple to one Store model class or stores table. Set `itmosoft-pages.stores_table` for documentation/integration purposes and enforce tenant ownership at the application boundary. Use the same store resolver middleware as `itmosoft/laravel-stores` and set request attribute `store_id` before public API requests.

## Create and publish a page

```php
use ItmoSoft\Pages\Services\PageManager;

$pages = app(PageManager::class);

$page = $pages->create(
    storeId: $storeId,
    attributes: [
        'title' => 'Privacy Policy',
        'slug' => 'privacy-policy',
        'type' => 'privacy-policy',
        'template' => 'legal',
        'content' => '<h2>Privacy</h2><p>Your policy content...</p>',
        'metadata' => ['meta_title' => 'Privacy Policy', 'meta_description' => 'How this store uses personal data.'],
        'show_in_navigation' => true,
        'navigation_location' => 'footer',
        'navigation_order' => 10,
    ],
    actorId: auth()->id(),
);

$pages->publish($page->id, $storeId, auth()->id());
```

The package stores content only. It does not generate legal advice or guarantee GDPR, CCPA, cookie-consent, accessibility, or other legal compliance. Store owners must supply and review jurisdiction-appropriate content.

## Admin integration (Livewire)

Build Livewire components in the host application rather than coupling this package to a specific admin UI. Use `PageManager::paginate`, `create`, `update`, `publish`, `unpublish`, `archive`, `delete`, `restoreRevision`, and the `Page`/`PageRevision` models. Pass the authenticated user's ID as `actorId` and derive `storeId` from trusted server-side tenant context, never from an untrusted form field. Use the current maximum revision version as `expectedVersion` on update to reject stale edits.

Recommended admin screens:

- Pages list with title, page type, store, status, updated timestamp and filters.
- Create/edit form with title, slug, type, template, rich-text editor, JSON blocks and metadata.
- Navigation settings with location and order.
- Preview (authenticated/authorized endpoint in the host app; do not publish drafts publicly).
- Revision history and restore.
- Publish, schedule, unpublish, archive, and soft-delete actions.

## Public API

The package registers:

- `GET /api/v1/pages/{slug}` — published page only.
- `GET /api/v1/pages/navigation/{location}` — published pages in a navigation location.

The routes read request attribute `store_id`. Install the store-resolution middleware before these routes (or wrap them in a route group using that middleware). The routes are public read endpoints; management endpoints are deliberately not exposed by this package and must be implemented behind your application's authentication, authorization, and tenant middleware.

Example Nuxt usage:

```ts
const config = useRuntimeConfig()
const { data } = await useFetch(`${config.public.apiBase}/api/v1/pages/privacy-policy`, {
  headers: { 'X-Store-Domain': useRequestURL().host },
})
```

Configure CORS and the host store-resolution middleware to match your deployment. Do not trust a client-provided store ID as authorization.

## Page types

Default types include standard, privacy-policy, terms-and-conditions, return-refund-policy, shipping-policy, cookie-policy, accessibility, disclaimer, faq, contact, order-tracking, size-guide, warranty, about, testimonials, store-locator, sustainability, affiliate, wholesale, gift-cards, careers, and custom-404. Add or remove types in `config/itmosoft-pages.php`.

Blog/resource-center publishing, FAQ question entities, testimonials/reviews, store locator data, contact submissions, order tracking, gift card balances, affiliate applications, and careers/job listings are functional domains and should use their dedicated packages/modules. This package can store informational landing pages for those features but does not implement those systems.

## Dependencies and integration boundaries

- `itmosoft/laravel-stores`: store resolution and tenant identity.
- `itmosoft/laravel-media`: image/file uploads referenced by content blocks (media IDs/URLs are stored as content data; this package does not upload files).
- `itmosoft/laravel-seo`: canonical/meta/social rendering using page metadata.
- `itmosoft/laravel-reviews`: actual verified customer reviews; testimonials landing-page content can link to it.

## Testing

```bash
composer validate --strict
composer install
composer test
```

## Security notes

- Admin CRUD routes are intentionally omitted; authorize all operations in the host application with policies and tenant checks.
- Treat rich HTML and content blocks as untrusted input. Sanitize HTML before rendering; use an allowlisted renderer for blocks. Nuxt must not render arbitrary HTML without sanitization.
- Avoid embedding secrets or customer PII in page content or metadata.
- Preview endpoints must be private, time-limited if externally shared, and must never expose draft content through public slug resolution.
