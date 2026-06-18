<?php

namespace Tests\Unit\ActivityLog;

use App\Models\Activity;
use App\Models\Concerns\SetsActivityProject;
use PHPUnit\Framework\TestCase;

class FakeLoggable
{
    use SetsActivityProject;

    public ?string $project_id = 'proj-123';
    protected array $activitySensitiveAttributes = ['password'];
}

class SetsActivityProjectTest extends TestCase
{
    public function test_tap_sets_project_id_from_resolver(): void
    {
        $model = new FakeLoggable();
        $activity = new Activity();
        $activity->attribute_changes = collect([]);

        $model->beforeActivityLogged($activity, 'created');

        $this->assertSame('proj-123', $activity->project_id);
    }

    public function test_tap_redacts_sensitive_attributes_in_attribute_changes(): void
    {
        $model = new FakeLoggable();
        $activity = new Activity();
        // In spatie/laravel-activitylog v5 the dirty/old bags live here.
        $activity->attribute_changes = collect([
            'attributes' => ['slug' => 'abc', 'password' => 'hashed-value'],
            'old' => ['password' => 'old-hash', 'slug' => 'old'],
        ]);

        $model->beforeActivityLogged($activity, 'updated');

        $changes = $activity->attribute_changes->toArray();
        $this->assertSame('••••', $changes['attributes']['password']);
        $this->assertSame('••••', $changes['old']['password']);
        $this->assertSame('abc', $changes['attributes']['slug']);
    }

    public function test_tap_redacts_sensitive_attributes_in_properties(): void
    {
        $model = new FakeLoggable();
        $activity = new Activity();
        // Defensive: secrets surfaced through custom withProperties() data.
        $activity->properties = collect([
            'attributes' => ['slug' => 'abc', 'password' => 'hashed-value'],
            'old' => ['password' => 'old-hash', 'slug' => 'old'],
        ]);

        $model->beforeActivityLogged($activity, 'updated');

        $props = $activity->properties->toArray();
        $this->assertSame('••••', $props['attributes']['password']);
        $this->assertSame('••••', $props['old']['password']);
        $this->assertSame('abc', $props['attributes']['slug']);
    }

    public function test_tap_leaves_null_sensitive_values_untouched(): void
    {
        $model = new FakeLoggable();
        $activity = new Activity();
        $activity->attribute_changes = collect(['attributes' => ['password' => null]]);

        $model->beforeActivityLogged($activity, 'updated');

        $this->assertNull($activity->attribute_changes->toArray()['attributes']['password']);
    }
}
