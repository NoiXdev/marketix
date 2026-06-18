<?php

namespace App\Models\Concerns;

use App\Models\Activity;
use Illuminate\Support\Collection;

trait SetsActivityProject
{
    /**
     * Hook called by spatie/laravel-activitylog v5 on the subject model after
     * the activity is built but before it is saved. Tags the activity with the
     * project and redacts sensitive attributes from the recorded changes.
     *
     * In v5 the dirty/old attribute bags live on $activity->attribute_changes;
     * $activity->properties holds only custom withProperties() data. We redact
     * both defensively in case a model surfaces secrets through either channel.
     */
    public function beforeActivityLogged(Activity $activity, string $eventName): void
    {
        $activity->project_id = $this->resolveActivityProjectId();

        $sensitive = $this->activitySensitiveAttributes ?? [];

        if ($sensitive === []) {
            return;
        }

        $activity->attribute_changes = $this->redactBags($activity->attribute_changes, $sensitive);
        $activity->properties = $this->redactBags($activity->properties, $sensitive);
    }

    /**
     * Replace non-null sensitive values inside the "attributes" and "old"
     * change bags of a given collection, returning a new collection.
     *
     * @param  array<int, string>  $sensitive
     */
    protected function redactBags(?Collection $changes, array $sensitive): Collection
    {
        $data = $changes?->toArray() ?? [];

        foreach (['attributes', 'old'] as $bag) {
            if (! isset($data[$bag]) || ! is_array($data[$bag])) {
                continue;
            }

            foreach ($sensitive as $attr) {
                if (array_key_exists($attr, $data[$bag]) && $data[$bag][$attr] !== null) {
                    $data[$bag][$attr] = '••••';
                }
            }
        }

        return collect($data);
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
