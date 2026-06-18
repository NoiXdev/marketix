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
        $activity->properties = collect([]);

        $model->tapActivity($activity, 'created');

        $this->assertSame('proj-123', $activity->project_id);
    }

    public function test_tap_redacts_sensitive_attributes_in_attributes_and_old(): void
    {
        $model = new FakeLoggable();
        $activity = new Activity();
        $activity->properties = collect([
            'attributes' => ['slug' => 'abc', 'password' => 'hashed-value'],
            'old' => ['password' => 'old-hash', 'slug' => 'old'],
        ]);

        $model->tapActivity($activity, 'updated');

        $props = $activity->properties->toArray();
        $this->assertSame('••••', $props['attributes']['password']);
        $this->assertSame('••••', $props['old']['password']);
        $this->assertSame('abc', $props['attributes']['slug']);
    }

    public function test_tap_leaves_null_sensitive_values_untouched(): void
    {
        $model = new FakeLoggable();
        $activity = new Activity();
        $activity->properties = collect(['attributes' => ['password' => null]]);

        $model->tapActivity($activity, 'updated');

        $this->assertNull($activity->properties->toArray()['attributes']['password']);
    }
}
