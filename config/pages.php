<?php

return [
    'pages_table' => 'pages',
    'revisions_table' => 'page_revisions',
    'stores_table' => 'stores',
    'store_model' => ItmoSoft\Stores\Models\Store::class,
    'allow_public_preview' => false,
    'default_per_page' => 20,
    'max_per_page' => 100,
    'content_max_length' => 200000,
    'title_max_length' => 255,
    'slug_max_length' => 180,
    'navigation_locations' => ['header', 'footer', 'legal', 'help'],
    'page_types' => [
        'standard', 'privacy-policy', 'terms-and-conditions', 'return-refund-policy',
        'shipping-policy', 'cookie-policy', 'accessibility', 'disclaimer', 'faq',
        'contact', 'order-tracking', 'size-guide', 'warranty', 'about', 'testimonials',
        'store-locator', 'sustainability', 'affiliate', 'wholesale', 'gift-cards',
        'careers', 'custom-404',
    ],
];
