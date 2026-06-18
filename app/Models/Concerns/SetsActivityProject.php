<?php

namespace App\Models\Concerns;

use App\Models\Activity;

trait SetsActivityProject
{
    /**
     * Called by Spatie's LogsActivity after building an activity for an
     * auto-logged event. Tags the project and redacts sensitive attributes.
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->project_id = $this->resolveActivityProjectId();

        $sensitive = $this->activitySensitiveAttributes ?? [];

        if ($sensitive === []) {
            return;
        }

        $properties = $activity->properties->toArray();

        foreach (['attributes', 'old'] as $bag) {
            if (! isset($properties[$bag]) || ! is_array($properties[$bag])) {
                continue;
            }

            foreach ($sensitive as $attr) {
                if (array_key_exists($attr, $properties[$bag]) && $properties[$bag][$attr] !== null) {
                    $properties[$bag][$attr] = '••••';
                }
            }
        }

        $activity->properties = collect($properties);
    }

    /**
     * Project this model's activity belongs to. Defaults to the model's
     * own project_id; the Project model overrides this to return its key.
     */
    public function resolveActivityProjectId(): ?string
    {
        return $this->project_id ?? null;
    }
}
