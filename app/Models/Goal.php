<?php

namespace App\Models;

use App\Enums\GoalType;
use App\Models\Concerns\SetsActivityProject;
use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use HasFactory;

    use HasUlids, LogsActivity, SetsActivityProject, SoftDeletes;

    protected $fillable = [
        'project_id',
        'site_id',
        'name',
        'type',
        'match_value',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('goal')
            ->logOnly(['name', 'type', 'match_value'])
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
            'type' => GoalType::class,
        ];
    }
}
