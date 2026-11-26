<?php

namespace ItmoSoft\Pages\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use ItmoSoft\Pages\Services\PageManager;

class PublicPageController
{
    public function show(Request $request, string $slug, PageManager $pages): JsonResponse
    {
        $storeId = $request->attributes->get('store_id');
        $page = $pages->findPublishedBySlug($slug, $storeId === null ? null : (int) $storeId);

        abort_unless($page, 404);

        return response()->json([
            'data' => [
                'title' => $page->title,
                'slug' => $page->slug,
                'type' => $page->type,
                'template' => $page->template,
                'content' => $page->content,
                'content_blocks' => $page->content_blocks,
                'metadata' => $page->metadata,
                'published_at' => $page->published_at?->toISOString(),
            ],
        ]);
    }

    public function navigation(Request $request, string $location, PageManager $pages): JsonResponse
    {
        $storeId = $request->attributes->get('store_id');
        return response()->json(['data' => $pages->navigation($location, $storeId === null ? null : (int) $storeId)]);
    }
}
