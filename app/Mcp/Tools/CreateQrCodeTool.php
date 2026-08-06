<?php

namespace App\Mcp\Tools;

use App\Actions\CreateQrCode;
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

#[Description('Create a new QR code in a project the caller has access to. Only URL-target QR codes are supported (non-URL content types such as vCard, email, phone, etc. are out of scope for this tool). A dynamic QR code is backed by a real short link and requires a domain; a static QR code just encodes the URL directly.')]
class CreateQrCodeTool extends Tool
{
    use ResolvesProject;

    public function __construct(private CreateQrCode $createQrCode) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $request->validate([
            'project' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'target_url' => ['required', 'url', 'max:2048'],
            'dynamic' => ['nullable', 'boolean'],
            'domain' => ['nullable', 'string'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash'],
        ]);

        $project = $this->resolveProject($request);

        if ($project === null) {
            return Response::error('Project not found or access denied.');
        }

        $dynamic = $request->boolean('dynamic');

        $data = [
            'name' => $request->get('name'),
            'type' => 'link',
            'is_dynamic' => $dynamic,
            'content' => ['url' => $request->get('target_url')],
        ];

        if ($dynamic) {
            $domain = $this->resolveDomain($project, (string) $request->get('domain', ''));

            if ($domain === null) {
                return Response::error('A domain (within this project) is required for dynamic QR codes.');
            }

            $slug = $request->get('slug');
            $slug = filled($slug) ? $slug : $this->generateUniqueSlug($domain);

            // Same slug-uniqueness constraint as QrCodeRequest, scoped to this domain.
            Validator::make(
                ['slug' => $slug],
                ['slug' => [
                    'required', 'string', 'max:255', 'alpha_dash',
                    Rule::unique('urls', 'slug')->where('domain_id', $domain->id),
                ]],
            )->validate();

            $data['domain_id'] = $domain->id;
            $data['slug'] = $slug;
        }

        // 'style' is intentionally omitted: CreateQrCode::handle() falls back
        // to its own DEFAULT_STYLE (mirrors QrCodeController's) when absent.
        $qrCode = $this->createQrCode->handle($project, $request->user(), $data);

        $payload = [
            'id' => $qrCode->id,
            'name' => $qrCode->name,
            'is_dynamic' => $qrCode->is_dynamic,
        ];

        if ($qrCode->is_dynamic) {
            $qrCode->loadMissing('url.domain');
            $payload['short_url'] = 'https://'.$qrCode->url->domain->name.'/'.$qrCode->url->slug;
        }

        return Response::text(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
                ->description('The project id or name to create the QR code in.')
                ->required(),
            'name' => $schema->string()
                ->description('A label for the QR code.')
                ->required(),
            'target_url' => $schema->string()
                ->description('The URL the QR code should point to.')
                ->required(),
            'dynamic' => $schema->boolean()
                ->description('Whether the QR code is backed by an editable short link (true) or encodes the URL directly (false).')
                ->default(false),
            'domain' => $schema->string()
                ->description('The id or name of the domain (within the project) to host the backing short link on. Required when dynamic is true.'),
            'slug' => $schema->string()
                ->description('The backing short link slug. Auto-generated and unique per domain when omitted. Only used when dynamic is true.'),
        ];
    }
}
