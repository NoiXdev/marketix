<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Support\VisitResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VisitResolverTest extends TestCase
{
    use RefreshDatabase;

    private function firstTouch(): array
    {
        return [
            'country_code' => 'DE', 'browser' => 'Chrome', 'os' => 'macOS', 'device' => 'Desktop',
            'referer_domain' => 'google.com',
            'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => null, 'utm_term' => null, 'utm_content' => null,
        ];
    }

    public function test_creates_a_new_visit_with_zero_pageviews_and_first_touch(): void
    {
        $site = Site::factory()->create();
        $resolver = new VisitResolver;

        $visit = $resolver->resolve($site->id, $site->project_id, 'v1', false, '/lp', $this->firstTouch());

        $this->assertSame(0, $visit->pageview_count);
        $this->assertSame('/lp', $visit->entry_path);
        $this->assertSame('/lp', $visit->exit_path);
        $this->assertSame('google', $visit->utm_source);
        $this->assertSame('Chrome', $visit->browser);
    }

    public function test_returns_existing_visit_within_window_unchanged(): void
    {
        $site = Site::factory()->create();
        $resolver = new VisitResolver;

        $first = $resolver->resolve($site->id, $site->project_id, 'v1', false, '/lp', $this->firstTouch());
        $second = $resolver->resolve($site->id, $site->project_id, 'v1', false, '/other', ['utm_source' => 'newsletter'] + $this->firstTouch());

        $this->assertSame($first->id, $second->id);
        $this->assertSame('/lp', $second->entry_path); // unchanged
        $this->assertSame('google', $second->utm_source); // first-touch preserved
        $this->assertSame(0, $second->pageview_count); // resolver never increments
    }

    public function test_opens_new_visit_after_window(): void
    {
        $site = Site::factory()->create();
        $resolver = new VisitResolver;

        Carbon::setTestNow(now());
        $a = $resolver->resolve($site->id, $site->project_id, 'v1', false, '/lp', $this->firstTouch());
        Carbon::setTestNow(now()->addMinutes(31));
        $b = $resolver->resolve($site->id, $site->project_id, 'v1', false, '/lp', $this->firstTouch());
        Carbon::setTestNow();

        $this->assertNotSame($a->id, $b->id);
    }

    public function test_a_later_visit_of_the_same_visitor_is_returning(): void
    {
        $site = Site::factory()->create();
        $other = Site::factory()->create();
        $resolver = new VisitResolver;

        Carbon::setTestNow(now());
        $first = $resolver->resolve($site->id, $site->project_id, 'v1', false, '/lp', $this->firstTouch());
        $sameSession = $resolver->resolve($site->id, $site->project_id, 'v1', false, '/other', $this->firstTouch());
        Carbon::setTestNow(now()->addDay());
        $later = $resolver->resolve($site->id, $site->project_id, 'v1', false, '/lp', $this->firstTouch());
        $otherSite = $resolver->resolve($other->id, $other->project_id, 'v1', false, '/lp', $this->firstTouch());
        Carbon::setTestNow();

        $this->assertFalse($first->is_returning);
        $this->assertSame($first->id, $sameSession->id);
        $this->assertTrue($later->is_returning);
        $this->assertFalse($otherSite->is_returning); // history is per site
    }

    public function test_after_consent_a_new_visit_keeps_only_the_campaign_source_of_the_anonymous_visit(): void
    {
        $site = Site::factory()->create();
        $resolver = new VisitResolver;

        $anonymous = $resolver->resolve($site->id, $site->project_id, 'anon', false, '/lp', $this->firstTouch());
        $resolver->handOver($site->id, 'anon', 'id');
        $identified = $resolver->resolve($site->id, $site->project_id, 'id', false, '/pricing', ['referer_domain' => 'example.com', 'utm_source' => null] + $this->firstTouch());

        $this->assertNotSame($anonymous->id, $identified->id);
        $this->assertSame('anon', $anonymous->fresh()->visitor_hash);
        $this->assertSame('/lp', $identified->entry_path);
        $this->assertSame('/pricing', $identified->exit_path);
        $this->assertSame('google.com', $identified->referer_domain);
        $this->assertSame('google', $identified->utm_source);
        $this->assertSame('cpc', $identified->utm_medium);
    }

    public function test_the_handover_applies_once_and_only_while_the_anonymous_visit_is_open(): void
    {
        $site = Site::factory()->create();
        $resolver = new VisitResolver;
        $direct = ['referer_domain' => null, 'utm_source' => null, 'utm_medium' => null] + $this->firstTouch();

        Carbon::setTestNow(now());
        $resolver->resolve($site->id, $site->project_id, 'anon', false, '/lp', $this->firstTouch());
        $resolver->handOver($site->id, 'anon', 'id');
        $resolver->resolve($site->id, $site->project_id, 'id', false, '/pricing', $direct);
        Carbon::setTestNow(now()->addMinutes(31));
        $later = $resolver->resolve($site->id, $site->project_id, 'id', false, '/pricing', $direct);

        $resolver->handOver($site->id, 'anon', 'other-id');
        $afterAnonymousEnded = $resolver->resolve($site->id, $site->project_id, 'other-id', false, '/pricing', $direct);
        Carbon::setTestNow();

        $this->assertSame('/pricing', $later->entry_path);
        $this->assertNull($later->utm_source);
        $this->assertSame('/pricing', $afterAnonymousEnded->entry_path);
        $this->assertNull($afterAnonymousEnded->utm_source);
    }
}
