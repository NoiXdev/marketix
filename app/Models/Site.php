<?php

namespace App\Models;

use App\Enums\ConsentMode;
use App\Enums\TrackingMode;
use App\Models\Concerns\SetsActivityProject;
use App\Support\Analytics\ChannelClassifier;
use Carbon\CarbonImmutable;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    protected $attributes = [
        'timezone' => 'UTC',
    ];

    protected $fillable = [
        'project_id',
        'name',
        'domain',
        'additional_hostnames',
        'timezone',
        'tracking_id',
        'tracking_mode',
        'consent_mode',
        'consent_signal',
        'respect_dnt',
        'track_outbound_links',
        'track_file_downloads',
        'track_contact_links',
        'site_search_params',
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
            ->logOnly(['name', 'domain', 'additional_hostnames', 'timezone', 'tracking_mode', 'consent_mode', 'consent_signal', 'respect_dnt', 'track_outbound_links', 'track_file_downloads', 'track_contact_links', 'site_search_params', 'retention_days'])
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

    public function funnels(): HasMany
    {
        return $this->hasMany(Funnel::class);
    }

    public function annotations(): HasMany
    {
        return $this->hasMany(Annotation::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'site_favorites');
    }

    protected function casts(): array
    {
        return [
            'tracking_mode' => TrackingMode::class,
            'consent_mode' => ConsentMode::class,
            'respect_dnt' => 'boolean',
            'track_outbound_links' => 'boolean',
            'track_file_downloads' => 'boolean',
            'track_contact_links' => 'boolean',
            'retention_days' => 'integer',
        ];
    }

    /**
     * The site's domain and any additional hostnames, without "www."; their
     * subdomains are accepted as well.
     *
     * @return list<string>
     */
    public function trackedDomains(): array
    {
        $domains = array_map(
            fn (string $domain) => ChannelClassifier::normalizeDomain($domain),
            [$this->domain, ...explode(',', (string) $this->additional_hostnames)],
        );

        return array_values(array_unique(array_filter($domains)));
    }

    public function acceptsHostname(string $hostname): bool
    {
        $hostname = strtolower($hostname);

        foreach ($this->trackedDomains() as $domain) {
            if ($hostname === $domain || str_ends_with($hostname, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function searchParams(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->site_search_params))));
    }

    public function trackingSnippet(): string
    {
        $src = rtrim((string) config('app.url'), '/').'/mx.js';

        return '<script defer data-site="'.e($this->tracking_id).'" src="'.e($src).'"></script>';
    }

    public function hasTrackedVisits(): bool
    {
        return $this->visits()->where('is_bot', false)->exists();
    }

    public function firstTrackedAt(): ?CarbonImmutable
    {
        $first = $this->visits()->where('is_bot', false)->min('started_at');

        return $first === null ? null : CarbonImmutable::parse($first, config('app.timezone'));
    }
}
