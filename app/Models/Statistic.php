<?php

namespace App\Models;

use Database\Factories\StatisticFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Statistic extends Model
{
    /** @use HasFactory<StatisticFactory> */
    use HasFactory;

    use HasUlids, SoftDeletes;

    protected $fillable = [
        'project_id',
        'url_id',
        'visitor_hash',
        'country',
        'country_code',
        'city',
        'language',
        'domain',
        'referer',
        'browser',
        'os',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function url(): BelongsTo
    {
        return $this->belongsTo(Url::class);
    }
}
