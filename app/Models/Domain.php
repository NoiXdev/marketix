<?php

namespace App\Models;

use App\Models\Concerns\SetsActivityProject;
use App\Observers\DomainObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[ObservedBy([DomainObserver::class])]
class Domain extends Model
{
    use HasFactory, HasUlids, LogsActivity, SetsActivityProject, SoftDeletes;

    protected $fillable = [
        'project_id',
        'name',
        'redirect_root',
        'redirect_not_found',
        'dns_ok',
        'reachable_ok',
        'ssl_ok',
        'check_details',
        'last_checked_at',
    ];

    protected $casts = [
        'dns_ok' => 'boolean',
        'reachable_ok' => 'boolean',
        'ssl_ok' => 'boolean',
        'check_details' => 'array',
        'last_checked_at' => 'datetime',
    ];

    protected $appends = ['status'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('domain')
            ->logOnly(['name', 'redirect_root', 'redirect_not_found'])
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

    public function getStatusAttribute(): string
    {
        if ($this->dns_ok === true && $this->reachable_ok === true && $this->ssl_ok === true) {
            return 'healthy';
        }

        if ($this->dns_ok === false || $this->reachable_ok === false || $this->ssl_ok === false) {
            return 'error';
        }

        return 'pending';
    }
}
