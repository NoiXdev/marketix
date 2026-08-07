<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledReport extends Model
{
    use HasUlids;

    protected $fillable = [
        'project_id',
        'created_by',
        'name',
        'type',
        'subject_id',
        'frequency',
        'weekday',
        'day_of_month',
        'period',
        'formats',
        'recipients',
        'active',
        'last_sent_at',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'formats' => 'array',
            'recipients' => 'array',
            'active' => 'bool',
            'last_sent_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
