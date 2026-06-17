<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class QrCode extends Model
{
    use SoftDeletes;

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
            'content'    => 'array',
            'style'      => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function url(): BelongsTo
    {
        return $this->belongsTo(Url::class);
    }

    /**
     * Render the vCard 3.0 payload from the stored content fields.
     * Mirrors the frontend buildVCard() in resources/js/data/qrTypes.ts.
     */
    public function vCardString(): string
    {
        $c = $this->content;

        return Collection::make([
            'BEGIN:VCARD', 'VERSION:3.0',
            ! empty($c['name'])    ? 'FN:'.$c['name']                    : null,
            ! empty($c['org'])     ? 'ORG:'.$c['org']                    : null,
            ! empty($c['phone'])   ? 'TEL:'.$c['phone']                  : null,
            ! empty($c['email'])   ? 'EMAIL:'.$c['email']                : null,
            ! empty($c['url'])     ? 'URL:'.$c['url']                    : null,
            ! empty($c['address']) ? 'ADR:;;'.$c['address'].';;;'        : null,
            'END:VCARD',
        ])->filter()->implode("\r\n");
    }
}
