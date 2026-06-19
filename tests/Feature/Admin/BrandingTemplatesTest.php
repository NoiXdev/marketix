<?php

namespace Tests\Feature\Admin;

use App\Mail\TestMail;
use App\Reports\ReportData;
use App\Settings\BrandingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Tests\TestCase;

class BrandingTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private function setBrand(string $name): void
    {
        $settings = app(BrandingSettings::class);
        $settings->app_name = $name;
        $settings->save();
        config(['app.name' => $settings->appName()]);
    }

    public function test_invitation_email_uses_custom_app_name(): void
    {
        $this->setBrand('Acme Links');

        // Render via a real Mailable so the mail:: component namespace is registered.
        $mailable = new class ('Demo', 'https://example.com/invitations/abc') extends Mailable {
            public function __construct(
                private string $projectName,
                private string $acceptUrl,
            ) {}

            public function envelope(): Envelope
            {
                return new Envelope(subject: 'Invitation test');
            }

            public function content(): Content
            {
                return new Content(
                    markdown: 'mail.project-invitation',
                    with: [
                        'projectName' => $this->projectName,
                        'acceptUrl' => $this->acceptUrl,
                    ],
                );
            }
        };

        $html = $mailable->render();

        $this->assertStringContainsString('Acme Links', $html);
        $this->assertStringNotContainsString('on Marketix', $html);
    }

    public function test_report_cover_uses_custom_app_name(): void
    {
        $this->setBrand('Acme Links');

        $data = new ReportData(
            scope: 'project',
            title: 'Statistics report — Acme',
            subtitle: 'Acme',
            rangeLabel: 'Last 30 days',
            generatedAt: '18 Jun 2026, 12:00',
            totalClicks: 1,
            uniqueClicks: 1,
            timeSeries: [['date' => '2026-06-18', 'clicks' => 1, 'unique' => 1]],
            breakdowns: ['country' => [], 'city' => [], 'browser' => [], 'os' => [], 'domain' => []],
            topLinks: [],
            recentClicks: [],
        );

        $html = view('reports.project', $data->toArray())->render();

        $this->assertStringContainsString('Acme Links', $html);
    }
}
