<?php

namespace App\Models;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Observers\UrlObserver;
use Database\Factories\UrlFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([UrlObserver::class])]
class Url extends Model
{
    /** @use HasFactory<UrlFactory> */
    use HasFactory;

    use SoftDeletes;

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
