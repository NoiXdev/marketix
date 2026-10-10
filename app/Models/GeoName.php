<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeoName extends Model
{
    protected $fillable = [
        'type',
        'country_code',
        'name',
        'names',
    ];

    protected function casts(): array
    {
        return [
            'names' => 'array',
        ];
    }
}
