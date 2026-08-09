<?php

namespace App\Models;

use Database\Factories\PageViewFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageView extends Model
{
    /** @use HasFactory<PageViewFactory> */
    use HasFactory;

    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'visit_id',
        'site_id',
        'project_id',
        'visitor_hash',
        'path',
        'referer',
        'referer_domain',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'country',
        'country_code',
        'city',
        'browser',
        'os',
        'device',
        'language',
        'is_bot',
        'created_at',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'is_bot' => 'boolean',
        ];
    }
}
