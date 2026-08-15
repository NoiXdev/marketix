<?php

namespace App\Models;

use Database\Factories\CrawlResourceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlResource extends Model
{
    /** @use HasFactory<CrawlResourceFactory> */
    use HasFactory, HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function fromPage(): BelongsTo
    {
        return $this->belongsTo(CrawlPage::class, 'from_page_id');
    }
}
