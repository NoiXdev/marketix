<?php

namespace App\Models;

use App\Enums\ConsentMode;
use App\Enums\TrackingMode;
use App\Models\Concerns\SetsActivityProject;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    use HasUlids, LogsActivity, SetsActivityProject, SoftDeletes;

    protected $fillable = [
        'project_id',
        'name',
        'domain',
        'tracking_id',
        'tracking_mode',
        'consent_mode',
        'consent_signal',
        'respect_dnt',
        'retention_days',
    ];

    protected static function booted(): void
    {
        static::creating(function (Site $site) {
            if (empty($site->tracking_id)) {
                $site->tracking_id = static::generateTrackingId();
            }
        });
    }

    public static function generateTrackingId(): string
    {
        do {
            $id = Str::lower(Str::random(16));
        } while (static::where('tracking_id', $id)->exists());

        return $id;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('site')
            ->logOnly(['name', 'domain', 'tracking_mode', 'consent_mode', 'consent_signal', 'respect_dnt', 'retention_days'])
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

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(PageView::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    protected function casts(): array
    {
        return [
            'tracking_mode' => TrackingMode::class,
            'consent_mode' => ConsentMode::class,
            'respect_dnt' => 'boolean',
            'retention_days' => 'integer',
        ];
    }
}
