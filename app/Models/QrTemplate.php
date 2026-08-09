<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrTemplate extends Model
{
    use HasUlids;

    protected $fillable = [
        'project_id',
        'name',
        'style',
    ];

    protected function casts(): array
    {
        return [
            'style' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
