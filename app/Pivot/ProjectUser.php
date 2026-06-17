<?php

namespace App\Pivot;

use App\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProjectUser extends Pivot
{
    protected $fillable = [
        'project_id',
        'user_id',
        'role',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'role' => ProjectRole::class,
            'active' => 'boolean',
        ];
    }
}
