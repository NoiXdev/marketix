<?php

namespace App\Http\Controllers;

use App\Enums\ControllerRole;
use App\Enums\LegalBasis;
use App\Http\Requests\PrivacyNoticeRequest;
use App\Models\Project;
use App\Support\Privacy\NoticeComposer;
use App\Support\Privacy\NoticeOptions;
use App\Support\Privacy\NoticeSection;
use App\Support\Privacy\NoticeWarnings;
use App\Support\Privacy\ProcessingSnapshot;

/**
 * Composes a privacy notice for the current project in the language the
 * generator form asks for, independently of the UI locale. Nothing is
 * persisted: the notice is rebuilt from current configuration on every
 * request so it can never go stale.
 */
class PrivacyNoticeController extends Controller
{
    public function __invoke(PrivacyNoticeRequest $request)
    {
        /** @var Project $project */
        $project = $request->get('project');

        $snapshot = ProcessingSnapshot::for($project);
        $options = $this->optionsFrom($request);
        $composer = new NoticeComposer;

        $sections = $composer->compose($snapshot, $options);

        return response()->json([
            'sections' => array_map(fn (NoticeSection $section) => [
                'heading' => $section->heading,
                'paragraphs' => $section->paragraphs,
            ], $sections),
            'text' => $composer->toPlainText($sections),
            'html' => $composer->toHtml($sections),
            'placeholders' => $composer->placeholders(),
            'warnings' => NoticeWarnings::for($snapshot, $options),
        ]);
    }

    private function optionsFrom(PrivacyNoticeRequest $request): NoticeOptions
    {
        return new NoticeOptions(
            role: ControllerRole::from($request->validated('role')),
            legalBasis: LegalBasis::from($request->validated('legal_basis')),
            locale: $request->validated('locale'),
            controllerName: $request->validated('controller_name'),
            controllerAddress: $request->validated('controller_address'),
            agencyName: $request->validated('agency_name'),
            agencyAddress: $request->validated('agency_address'),
            hostingProvider: $request->validated('hosting_provider'),
            hostingLocation: $request->validated('hosting_location'),
            disabledActivities: $request->validated('disabled_activities') ?? [],
        );
    }
}
