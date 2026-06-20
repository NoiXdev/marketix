<?php

namespace App\Models;

use App\Models\Concerns\SetsActivityProject;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class QrCode extends Model
{
    use HasUlids, LogsActivity, SetsActivityProject, SoftDeletes;

    protected $fillable = [
        'project_id',
        'url_id',
        'name',
        'type',
        'is_dynamic',
        'content',
        'style',
    ];

    protected function casts(): array
    {
        return [
            'is_dynamic' => 'boolean',
            'content' => 'array',
            'style' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('qrcode')
            ->logOnly(['name', 'type', 'is_dynamic', 'content', 'style'])
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

    public function url(): BelongsTo
    {
        return $this->belongsTo(Url::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(QrCodeVersion::class);
    }

    /**
     * Render the vCard 3.0 payload from the stored content fields.
     * Mirrors the frontend buildVCard() in resources/js/data/qrTypes.ts.
     */
    public function vCardString(): string
    {
        $c = $this->content;

        $extra = Collection::make(explode("\n", (string) ($c['extra'] ?? '')))
            ->map(fn (string $line) => trim($line))
            ->filter();

        return Collection::make([
            'BEGIN:VCARD', 'VERSION:3.0',
            ! empty($c['name']) ? 'FN:'.$c['name'] : null,
            ! empty($c['org']) ? 'ORG:'.$c['org'] : null,
            ! empty($c['phone']) ? 'TEL:'.$c['phone'] : null,
            ! empty($c['email']) ? 'EMAIL:'.$c['email'] : null,
            ! empty($c['url']) ? 'URL:'.$c['url'] : null,
            ! empty($c['address']) ? 'ADR:;;'.$c['address'].';;;' : null,
        ])
            ->filter()
            ->concat($extra)
            ->push('END:VCARD')
            ->implode("\r\n");
    }
}
