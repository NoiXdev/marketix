<?php

namespace App\Models;

use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'site_id',
        'project_id',
        'visitor_hash',
        'started_at',
        'last_activity_at',
        'pageview_count',
        'entry_path',
        'exit_path',
        'country_code',
        'browser',
        'os',
        'device',
        'referer_domain',
        'is_bot',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(PageView::class);
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'pageview_count' => 'integer',
            'is_bot' => 'boolean',
        ];
    }
}
