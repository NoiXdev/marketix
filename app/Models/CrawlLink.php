<?php

namespace App\Models;

use Database\Factories\CrawlLinkFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlLink extends Model
{
    /** @use HasFactory<CrawlLinkFactory> */
    use HasFactory, HasUlids;

    protected $guarded = ['id'];

    public function crawl(): BelongsTo
    {
        return $this->belongsTo(Crawl::class);
    }

    public function fromPage(): BelongsTo
    {
        return $this->belongsTo(CrawlPage::class, 'from_page_id');
    }
}
