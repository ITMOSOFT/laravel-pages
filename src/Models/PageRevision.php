<?php

namespace ItmoSoft\Pages\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageRevision extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return (string) config('itmosoft-pages.revisions_table', 'page_revisions');
    }

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id');
    }
}
