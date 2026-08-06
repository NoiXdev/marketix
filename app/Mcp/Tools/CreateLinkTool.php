<?php

namespace App\Mcp\Tools;

use App\Actions\CreateLink;
use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Mcp\Concerns\ResolvesProject;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Url;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new short link in a project the caller has access to. Goes through the same validation as the web UI (valid target URL, alpha-dash slug unique per domain).')]
class CreateLinkTool extends Tool
{
    use ResolvesProject;

    public function __construct(private CreateLink $createLink) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $request->validate([
            'project' => ['required', 'string'],
            'domain' => ['required', 'string'],
            'url' => ['required', 'url', 'max:2048'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash'],
            'type' => ['nullable', 'integer', Rule::in(array_column(RedirectType::cases(), 'value'))],
            'expires_at' => ['nullable', 'date'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        $project = $this->resolveProject($request);

        if ($project === null) {
            return Response::error('Project not found or access denied.');
        }

        $domain = $this->resolveDomain($project, (string) $request->get('domain'));

        if ($domain === null) {
            return Response::error('Domain not found in this project.');
        }

        $slug = $request->get('slug');
        $slug = filled($slug) ? $slug : $this->generateUniqueSlug($domain);

        // Same slug-uniqueness constraint as UrlRequest, scoped to this domain.
        Validator::make(
            ['slug' => $slug],
            ['slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('urls', 'slug')->where('domain_id', $domain->id),
            ]],
        )->validate();

        $data = [
            'domain_id' => $domain->id,
            'slug' => $slug,
            'url' => $request->get('url'),
            'type' => $request->get('type', RedirectType::REDIRECT->value),
            'status' => UrlStatus::ACTIVATED->value,
            'password' => $request->get('password'),
            'expired_at' => $request->get('expires_at'),
        ];

        $url = $this->createLink->handle($project, $request->user(), $data);

        return Response::text(json_encode([
            'id' => $url->id,
            'slug' => $url->slug,
            'url' => $url->url,
            'short_url' => 'https://'.$domain->name.'/'.$url->slug,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Resolve a project's domain by id or name (case-insensitively).
     */
    private function resolveDomain(Project $project, string $value): ?Domain
    {
        if ($value === '') {
            return null;
        }

        return $project->domains->first(
            fn (Domain $domain): bool => (string) $domain->id === $value
                || strcasecmp($domain->name, $value) === 0,
        );
    }

    /**
     * Generate a random slug that is not yet taken on the given domain.
     */
    private function generateUniqueSlug(Domain $domain): string
    {
        do {
            $slug = Str::lower(Str::random(8));
        } while (Url::where('domain_id', $domain->id)->where('slug', $slug)->exists());

        return $slug;
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
                ->description('The project id or name to create the link in.')
                ->required(),
            'domain' => $schema->string()
                ->description('The id or name of the domain (within the project) the link should live on.')
                ->required(),
            'url' => $schema->string()
                ->description('The destination URL the short link should redirect to.')
                ->required(),
            'slug' => $schema->string()
                ->description('The short link slug. Auto-generated and unique per domain when omitted.'),
            'type' => $schema->integer()
                ->description('The redirect type. Only 0 (standard redirect) is currently supported.')
                ->default(RedirectType::REDIRECT->value),
            'expires_at' => $schema->string()
                ->description('Optional ISO-8601 date/time after which the link stops working.'),
            'password' => $schema->string()
                ->description('Optional password required to follow the link.'),
        ];
    }
}
