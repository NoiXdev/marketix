<?php

namespace Tests\Feature;

use App\Reports\ReportData;
use Tests\TestCase;

class ReportTemplateRenderTest extends TestCase
{
    private function sampleData(string $scope): ReportData
    {
        return new ReportData(
            scope: $scope,
            title: $scope === 'link' ? 'Link report — /go' : 'Statistics report — Acme',
            subtitle: 'Acme',
            rangeLabel: 'Last 30 days',
            generatedAt: '18 Jun 2026, 12:00',
            totalClicks: 42,
            uniqueClicks: 30,
            timeSeries: [['date' => '2026-06-18', 'clicks' => 42, 'unique' => 30]],
            breakdowns: ['country' => [['label' => 'Germany', 'count' => 42]], 'city' => [], 'browser' => [], 'os' => [], 'domain' => []],
            topLinks: $scope === 'project' ? [['slug' => 'go', 'domain' => 'acme.test', 'clicks' => 42]] : [],
            recentClicks: $scope === 'link' ? [['country' => 'Germany', 'city' => 'Berlin', 'browser' => 'Chrome', 'os' => 'macOS', 'domain' => null, 'created_at' => '2026-06-18 10:00:00']] : [],
        );
    }

    public function test_project_template_renders_kpis_and_chart_payload(): void
    {
        $html = view('reports.project', $this->sampleData('project')->toArray())->render();

        $this->assertStringContainsString('Statistics report — Acme', $html);
        $this->assertStringContainsString('42</div>', $html);
        $this->assertStringContainsString('Germany', $html);
        $this->assertStringContainsString('id="clicksChart"', $html);
        $this->assertStringContainsString('go', $html);
    }

    public function test_link_template_renders_recent_clicks(): void
    {
        $html = view('reports.link', $this->sampleData('link')->toArray())->render();

        $this->assertStringContainsString('Link report', $html);
        $this->assertStringContainsString('Berlin', $html);
    }
}
