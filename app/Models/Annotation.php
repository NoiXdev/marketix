<?php

namespace App\Models;

use App\Models\Concerns\SetsActivityProject;
use Database\Factories\AnnotationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Annotation extends Model
{
    /** @use HasFactory<AnnotationFactory> */
    use HasFactory;

    use HasUlids, LogsActivity, SetsActivityProject, SoftDeletes;

    public const TITLE_MAX = 120;

    public const DESCRIPTION_MAX = 2000;

    protected $fillable = [
        'project_id',
        'site_id',
        'user_id',
        'date',
        'title',
        'description',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('annotation')
            ->logOnly(['date', 'title', 'description'])
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toAnalyticsArray(): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->toDateString(),
            'title' => $this->title,
            'description' => $this->description,
            'author' => $this->user?->name,
        ];
    }

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
        ];
    }
}
