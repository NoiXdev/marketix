<?php

namespace Tests\Feature\Reports;

use Tests\TestCase;

/**
 * Smoke tests for the report Blade templates: renders each view with a
 * representative viewData array (matching the shapes produced by
 * ReportData::toArray() and SiteAnalyticsReport::viewData()) and asserts
 * a non-empty string containing a known label. Plain Blade render only —
 * Browsershot is never invoked here.
 */
class ReportViewsTest extends TestCase
{
    /**
     * @return array<string,mixed>
     */
    private function clickViewData(string $scope): array
    {
        return [
            'scope' => $scope,
            'title' => $scope === 'project' ? 'Statistics report — Acme' : 'Link report — /go',
            'subtitle' => $scope === 'project' ? 'Acme' : 'https://acme.example.test',
            'rangeLabel' => 'Last 7 days',
            'generatedAt' => '7 Aug 2026, 12:00',
            'totalClicks' => 42,
            'uniqueClicks' => 30,
            'timeSeries' => [
                ['date' => '2026-08-01', 'clicks' => 5, 'unique' => 4],
                ['date' => '2026-08-02', 'clicks' => 7, 'unique' => 6],
            ],
            'breakdowns' => [
                'country' => [['label' => 'Germany', 'count' => 20]],
                'city' => [['label' => 'Berlin', 'count' => 10]],
                'browser' => [['label' => 'Chrome', 'count' => 25]],
                'os' => [['label' => 'macOS', 'count' => 15]],
                'domain' => [['label' => 'ref.test', 'count' => 5]],
            ],
            'topLinks' => $scope === 'project'
                ? [['slug' => 'go', 'domain' => 'acme.test', 'clicks' => 30]]
                : [],
            'recentClicks' => $scope === 'link'
                ? [['country' => 'Germany', 'city' => 'Berlin', 'browser' => 'Chrome', 'os' => 'macOS', 'domain' => 'ref.test', 'created_at' => '2026-08-01 10:00:00']]
                : [],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyClickViewData(string $scope): array
    {
        return array_merge($this->clickViewData($scope), [
            'timeSeries' => [],
            'breakdowns' => [
                'country' => [], 'city' => [], 'browser' => [], 'os' => [], 'domain' => [],
            ],
            'topLinks' => [],
            'recentClicks' => [],
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function siteViewData(): array
    {
        return [
            'title' => 'Site analytics report — Marketing site',
            'subtitle' => 'Marketing site',
            'rangeLabel' => 'Last 7 days',
            'generatedAt' => '7 Aug 2026, 12:00',
            'visits' => 120,
            'page_views' => 340,
            'goals' => [
                ['name' => 'Signup', 'conversions' => 10, 'visitors' => 100, 'rate' => 10.0],
            ],
            'top_events' => [
                ['name' => 'signup', 'count' => 12, 'visitors' => 11],
            ],
            'series' => [
                ['date' => '2026-08-01', 'views' => 40, 'visitors' => 20],
                ['date' => '2026-08-02', 'views' => 55, 'visitors' => 25],
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function emptySiteViewData(): array
    {
        return array_merge($this->siteViewData(), [
            'goals' => [],
            'top_events' => [],
            'series' => [],
        ]);
    }

    // --- PDF views ------------------------------------------------------

    public function test_pdf_project_view_renders(): void
    {
        $html = view('reports.project', $this->clickViewData('project'))->render();

        $this->assertNotSame('', $html);
        $this->assertStringContainsString('Total clicks', $html);
        $this->assertStringContainsString('Top links', $html);
    }

    public function test_pdf_link_view_renders(): void
    {
        $html = view('reports.link', $this->clickViewData('link'))->render();

        $this->assertNotSame('', $html);
        $this->assertStringContainsString('Total clicks', $html);
        $this->assertStringContainsString('Recent clicks', $html);
    }

    public function test_pdf_site_view_renders(): void
    {
        $html = view('reports.site', $this->siteViewData())->render();

        $this->assertNotSame('', $html);
        $this->assertStringContainsString('Visits', $html);
        $this->assertStringContainsString('Goals', $html);
        $this->assertStringContainsString('Top events', $html);
    }

    // --- Email views ------------------------------------------------------

    public function test_email_project_view_renders(): void
    {
        $html = view('reports.email.project', $this->clickViewData('project'))->render();

        $this->assertNotSame('', $html);
        $this->assertStringContainsString(__('reports.report.total_clicks'), $html);
        $this->assertStringContainsString(__('reports.report.top_links'), $html);
    }

    public function test_email_link_view_renders(): void
    {
        $html = view('reports.email.link', $this->clickViewData('link'))->render();

        $this->assertNotSame('', $html);
        $this->assertStringContainsString(__('reports.report.total_clicks'), $html);
        $this->assertStringContainsString('Recent clicks', $html);
    }

    public function test_email_site_view_renders(): void
    {
        $html = view('reports.email.site', $this->siteViewData())->render();

        $this->assertNotSame('', $html);
        $this->assertStringContainsString(__('reports.report.visits'), $html);
        $this->assertStringContainsString(__('reports.report.goals'), $html);
    }

    // --- Empty data tolerance ---------------------------------------------

    public function test_views_tolerate_empty_data(): void
    {
        $emptyProject = $this->emptyClickViewData('project');
        $emptyLink = $this->emptyClickViewData('link');
        $emptySite = $this->emptySiteViewData();

        $this->assertNotSame('', view('reports.project', $emptyProject)->render());
        $this->assertNotSame('', view('reports.link', $emptyLink)->render());
        $this->assertNotSame('', view('reports.site', $emptySite)->render());

        $this->assertNotSame('', view('reports.email.project', $emptyProject)->render());
        $this->assertNotSame('', view('reports.email.link', $emptyLink)->render());
        $this->assertNotSame('', view('reports.email.site', $emptySite)->render());
    }
}
