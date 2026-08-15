<?php

namespace App\Models;

use Database\Factories\CrawlPageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrawlPage extends Model
{
    /** @use HasFactory<CrawlPageFactory> */
    use HasFactory, HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'redirect_chain' => 'array',
            'headings' => 'array',
            'structured_data' => 'array',
            'images_missing_alt' => 'array',
            'issues' => 'array',
            'security_headers' => 'array',
            'is_indexable' => 'boolean',
            'in_sitemap' => 'boolean',
            'is_orphan' => 'boolean',
        ];
    }

    public function crawl(): BelongsTo
    {
        return $this->belongsTo(Crawl::class);
    }

    public function outLinks(): HasMany
    {
        return $this->hasMany(CrawlLink::class, 'from_page_id');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(CrawlResource::class, 'from_page_id');
    }
}
