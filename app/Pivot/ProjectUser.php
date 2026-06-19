<?php

namespace App\Pivot;

use App\Enums\ProjectRole;
use App\Enums\ReportFrequency;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProjectUser extends Pivot
{
    protected $fillable = [
        'project_id',
        'user_id',
        'role',
        'active',
        'report_frequency',
    ];

    protected function casts(): array
    {
        return [
            'role' => ProjectRole::class,
            'active' => 'boolean',
            'report_frequency' => ReportFrequency::class,
        ];
    }
}
