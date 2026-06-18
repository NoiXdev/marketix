<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeForProject(Builder $query, Project|string $project): Builder
    {
        return $query->where('project_id', $project instanceof Project ? $project->getKey() : $project);
    }

    public function toFeedArray(): array
    {
        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'description' => $this->description,
            'event' => $this->event,
            'subject_type' => $this->subject_type ? class_basename($this->subject_type) : null,
            'causer' => $this->causer ? ['id' => $this->causer->id, 'name' => $this->causer->name] : null,
            'properties' => $this->properties->toArray(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
