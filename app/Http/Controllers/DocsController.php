<?php

namespace App\Http\Controllers;

use App\Enums\ControllerRole;
use App\Enums\LegalBasis;
use App\Models\Project;
use App\Settings\OperatorSettings;
use App\Support\Privacy\ProcessingSnapshot;
use Illuminate\Http\Request;

class DocsController extends Controller
{
    /**
     * Static "Data Privacy" help page. Content is rendered from translations;
     * the dynamic values below keep the retention figures in sync with config.
     * It also doubles as the privacy notice generator, so it carries the
     * processing snapshot, party defaults and the generator form's defaults.
     */
    public function dataPrivacy(Request $request, OperatorSettings $settings)
    {
        /** @var Project $project */
        $project = $request->get('project');

        return inertia('Docs/DataPrivacy', [
            'appName' => config('app.name'),
            'statsMonths' => (int) config('statistics.retention_months'),
            'analyticsMonths' => (int) config('analytics.retention_months'),
            'snapshot' => ProcessingSnapshot::for($project)->toArray(),
            'parties' => $this->parties($project, $settings),
            'canEditClient' => (bool) $request->user()->super_admin,
            'defaults' => [
                'role' => $this->hasClientDetails($project)
                    ? ControllerRole::Client->value
                    : ControllerRole::OwnCompany->value,
                'locale' => app()->getLocale(),
                'legalBasis' => LegalBasis::LegitimateInterest->value,
            ],
        ]);
    }

    /** @return array<string, ?string> */
    private function parties(Project $project, OperatorSettings $settings): array
    {
        return [
            'agency_name' => $settings->agency_name,
            'agency_address' => $settings->agency_address,
            'hosting_provider' => $settings->hosting_provider,
            'hosting_location' => $settings->hosting_location,
            'client_name' => $project->client_name,
            'client_address' => $project->client_address,
        ];
    }

    private function hasClientDetails(Project $project): bool
    {
        return trim((string) $project->client_name) !== ''
            || trim((string) $project->client_address) !== '';
    }
}
