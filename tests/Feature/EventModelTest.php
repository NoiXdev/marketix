<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Site;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_an_event_with_json_props(): void
    {
        $site = Site::factory()->create();
        $visit = Visit::factory()->forSite($site)->create();

        $event = Event::factory()->forVisit($visit)->create([
            'name' => 'purchase',
            'props' => ['plan' => 'pro', 'value' => 49],
            'path' => '/checkout',
        ]);

        $fresh = $event->fresh();
        $this->assertSame('purchase', $fresh->name);
        $this->assertSame(['plan' => 'pro', 'value' => 49], $fresh->props);
        $this->assertSame($visit->id, $fresh->visit->id);
        $this->assertSame($site->id, $fresh->site->id);
        $this->assertFalse($fresh->is_bot);
    }

    public function test_props_default_null(): void
    {
        $this->assertNull(Event::factory()->create()->fresh()->props);
    }
}
