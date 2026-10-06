<?php

namespace App\Models;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Models\Concerns\SetsActivityProject;
use App\Observers\UrlObserver;
use App\Support\UtmTagger;
use Database\Factories\UrlFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[ObservedBy([UrlObserver::class])]
class Url extends Model
{
    /** @use HasFactory<UrlFactory> */
    use HasFactory;

    use HasUlids, LogsActivity, SetsActivityProject, SoftDeletes;

    protected $fillable = [
        'project_id',
        'domain_id',
        'user_id',
        'slug',
        'url',
        'type',
        'password',
        'expired_at',
        'clicks',
        'unique_clicks',
        'status',
        'archived',
        'targeting_geo',
        'targeting_device',
        'targeting_language',
        'targeting_ab',
        'utm',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pixels(): BelongsToMany
    {
        return $this->belongsToMany(Pixel::class);
    }

    public function qrCode(): HasOne
    {
        return $this->hasOne(QrCode::class);
    }

    protected array $activitySensitiveAttributes = ['password'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('url')
            ->logOnly([
                'slug', 'url', 'type', 'password', 'expired_at', 'status', 'archived',
                'targeting_geo', 'targeting_device', 'targeting_language', 'targeting_ab', 'utm',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return $eventName;
    }

    /**
     * UTM parameters appended to the redirect target, stored without blanks
     * (null when none are set) so every caller gets the same normal form.
     */
    protected function utm(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : json_decode($value, true),
            set: fn (?array $value) => ($utm = UtmTagger::normalize($value)) === null ? null : json_encode($utm),
        );
    }

    protected function casts(): array
    {
        return [
            'status' => UrlStatus::class,
            'type' => RedirectType::class,
            'password' => 'hashed',
            'archived' => 'boolean',
            'expired_at' => 'datetime',
            'targeting_geo' => 'array',
            'targeting_device' => 'array',
            'targeting_language' => 'array',
            'targeting_ab' => 'array',
        ];
    }
}
