<?php

namespace App\Models;

use App\Enums\PixelProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Pixel extends Model
{
    protected $fillable = [
        'project_id',
        'provider',
        'name',
        'tag',
    ];

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
