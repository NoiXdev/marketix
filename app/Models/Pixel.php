<?php

namespace App\Models;

use App\Enums\PixelProvider;
use App\Models\Concerns\SetsActivityProject;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Pixel extends Model
{
    use HasUlids, LogsActivity, SetsActivityProject;

    protected $fillable = [
        'project_id',
        'provider',
        'name',
        'tag',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pixel')
            ->logOnly(['provider', 'name', 'tag'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return $eventName;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function urls(): BelongsToMany
    {
        return $this->belongsToMany(Url::class);
    }

    protected function casts(): array
    {
        return [
            'provider' => PixelProvider::class,
        ];
    }
}
