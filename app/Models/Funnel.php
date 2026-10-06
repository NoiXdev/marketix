<?php

namespace App\Models;

use App\Models\Concerns\SetsActivityProject;
use Database\Factories\FunnelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Funnel extends Model
{
    /** @use HasFactory<FunnelFactory> */
    use HasFactory;

    use HasUlids, LogsActivity, SetsActivityProject, SoftDeletes;

    public const MIN_STEPS = 2;

    public const MAX_STEPS = 8;

    protected $fillable = [
        'project_id',
        'site_id',
        'name',
        'steps',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('funnel')
            ->logOnly(['name', 'steps'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return $eventName;
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    protected function casts(): array
    {
        return [
            'steps' => 'array',
        ];
    }
}
