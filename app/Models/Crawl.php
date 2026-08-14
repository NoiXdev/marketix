<?php

namespace App\Models;

use App\Enums\CrawlMode;
use App\Enums\CrawlStatus;
use Database\Factories\CrawlFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Crawl extends Model
{
    /** @use HasFactory<CrawlFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'project_id', 'site_id', 'start_url', 'mode', 'render_js', 'respect_robots',
        'include_subdomains', 'crawl_sitemap', 'capture_screenshots', 'delay_ms', 'max_pages', 'status', 'pages_crawled',
        'error', 'summary', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'mode' => CrawlMode::class,
            'status' => CrawlStatus::class,
            'render_js' => 'boolean',
            'respect_robots' => 'boolean',
            'include_subdomains' => 'boolean',
            'crawl_sitemap' => 'boolean',
            'capture_screenshots' => 'boolean',
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(CrawlPage::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(CrawlLink::class);
    }
}
