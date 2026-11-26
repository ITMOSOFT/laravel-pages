<?php

use Illuminate\Support\Facades\Route;
use ItmoSoft\Pages\Http\Controllers\PublicPageController;

Route::middleware('api')->prefix('api/v1/pages')->group(function (): void {
    Route::get('/navigation/{location}', [PublicPageController::class, 'navigation'])->name('itmosoft.pages.navigation');
    Route::get('/{slug}', [PublicPageController::class, 'show'])->name('itmosoft.pages.show');
});
