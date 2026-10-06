<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesProject;
use App\Models\Site;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List the websites tracked with web analytics in a project the caller has access to.')]
class ListSitesTool extends Tool
{
    use ResolvesProject;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $request->validate([
            'project' => ['required', 'string'],
        ]);

        $project = $this->resolveProject($request);

        if ($project === null) {
            return Response::error('Project not found or access denied.');
        }

        $sites = $project->sites()
            ->orderBy('name')
            ->get()
            ->map(fn (Site $site): array => [
                'id' => $site->id,
                'name' => $site->name,
                'domain' => $site->domain,
                'tracking_mode' => $site->tracking_mode->value,
                'has_data' => $site->hasTrackedVisits(),
            ])
            ->values()
            ->all();

        return Response::text(json_encode($sites, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()
                ->description('The project id or name to list websites for.')
                ->required(),
        ];
    }
}
