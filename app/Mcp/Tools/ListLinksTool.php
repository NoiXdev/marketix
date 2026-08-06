<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesProject;
use App\Models\Url;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List the links (short URLs) belonging to a project the caller has access to.')]
class ListLinksTool extends Tool
{
    use ResolvesProject;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $request->validate([
            'project' => ['required', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1'],
        ]);

        $project = $this->resolveProject($request);

        if ($project === null) {
            return Response::error('Project not found or access denied.');
        }

        $limit = min((int) $request->get('limit', 50), 200);

        $links = $project->urls()
            ->with('domain')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Url $url): array => [
                'id' => $url->id,
                'slug' => $url->slug,
                'url' => $url->url,
                'domain' => $url->domain?->name,
                'type' => $url->type->value,
                'status' => $url->status->value,
                'clicks' => $url->clicks,
                'unique_clicks' => $url->unique_clicks,
                'created_at' => $url->created_at->toISOString(),
            ])
            ->values()
            ->all();

        return Response::text(json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
                ->description('The project id or name to list links for.')
                ->required(),
            'limit' => $schema->integer()
                ->description('Maximum number of links to return (capped at 200).')
                ->default(50),
        ];
    }
}
