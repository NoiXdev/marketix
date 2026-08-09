<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'visit_id',
        'site_id',
        'project_id',
        'visitor_hash',
        'name',
        'props',
        'path',
        'is_bot',
        'created_at',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    protected function casts(): array
    {
        return [
            'props' => 'array',
            'is_bot' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
