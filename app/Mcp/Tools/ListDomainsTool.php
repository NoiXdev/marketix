<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesProject;
use App\Models\Domain;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List the custom domains belonging to a project the caller has access to.')]
class ListDomainsTool extends Tool
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

        $domains = $project->domains()
            ->latest()
            ->get()
            ->map(fn (Domain $domain): array => [
                'id' => $domain->id,
                'name' => $domain->name,
            ])
            ->values()
            ->all();

        return Response::text(json_encode($domains, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
                ->description('The project id or name to list domains for.')
                ->required(),
        ];
    }
}
