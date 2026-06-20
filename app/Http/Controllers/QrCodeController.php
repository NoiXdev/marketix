<?php

namespace App\Http\Controllers;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Http\Requests\QrCodeRequest;
use App\Models\Project;
use App\Models\QrCode;
use App\Models\Url;
use App\Support\QrTarget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class QrCodeController extends Controller
{
    // ── Default style applied to new QR codes ─────────────────────────────
    private array $defaultStyle = [
        'foreground' => '#000000',
        'background' => '#ffffff',
        'dot_style' => 'square',
        'corner_square_style' => 'square',
        'corner_dot_style' => 'square',
        'logo_type' => 'none',
        'logo_name' => '',
        'logo_data' => '',
        'logo_size' => 30,
    ];

    // ── CRUD ──────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $project = $request->get('project');

        return inertia('QrCodes/Index', [
            'qrCodes' => $project->qrCodes()->with('url')->latest()->get()->map(fn ($q) => [
                'id' => $q->id,
                'name' => $q->name,
                'type' => $q->type,
                'is_dynamic' => $q->is_dynamic,
                'scans' => $q->url?->clicks ?? 0,
                'unique_scans' => $q->url?->unique_clicks ?? 0,
                'created_at' => $q->created_at->toISOString(),
            ]),
        ]);
    }

    public function create(Request $request)
    {
        $project = $request->get('project');

        $attachUrl = null;
        if ($linkId = $request->query('link')) {
            $link = $project->urls()->with(['domain', 'qrCode'])->findOrFail($linkId);

            // Already has a QR — send the user to that QR instead of attaching a second.
            if ($link->qrCode !== null) {
                return redirect()
                    ->route('app.project.qrcodes.edit', ['project' => $project->id, 'qrCode' => $link->qrCode->id])
                    ->with('success', 'This link already has a QR code.');
            }

            $attachUrl = [
                'id' => $link->id,
                'domain_id' => $link->domain_id,
                'slug' => $link->slug,
                'domain_name' => $link->domain?->name,
                'target' => $link->url,
            ];
        }

        return inertia('QrCodes/Create', [
            'defaultStyle' => $this->defaultStyle,
            'domains' => $project->domains()->get(['id', 'name']),
            'attachUrl' => $attachUrl,
        ]);
    }

    public function store(QrCodeRequest $request)
    {
        $project = $request->get('project');
        $data = $request->validated();

        DB::transaction(function () use ($project, $data) {
            $urlId = null;

            if ($data['is_dynamic']) {
                if (! empty($data['url_id'])) {
                    // Attach mode: reuse the existing link as-is. Do not create
                    // a new Url and do not modify the existing one.
                    $urlId = $data['url_id'];
                } else {
                    $urlId = $project->urls()->create([
                        'domain_id' => $data['domain_id'],
                        'slug' => $data['slug'],
                        'url' => $this->backingTarget($project, $data),
                        'type' => RedirectType::REDIRECT,
                        'status' => UrlStatus::ACTIVATED,
                    ])->id;
                }
            }

            $qr = $project->qrCodes()->create([
                'url_id' => $urlId,
                'name' => $data['name'],
                'type' => $data['type'],
                'is_dynamic' => $data['is_dynamic'],
                'content' => $data['content'],
                'style' => $data['style'],
            ]);

            $this->recordVersion($qr);
        });

        return redirect()->route('app.project.qrcodes.index')
            ->with('success', 'QR code created.');
    }

    public function edit(Request $request, string $qrCode)
    {
        $project = $request->get('project');
        $model = $project->qrCodes()->with('url.domain')->findOrFail($qrCode);

        return inertia('QrCodes/Edit', [
            'qrCode' => [
                'id' => $model->id,
                'name' => $model->name,
                'type' => $model->type,
                'is_dynamic' => $model->is_dynamic,
                'content' => $model->content,
                'style' => $model->style,
                'domain_id' => $model->url?->domain_id,
                'slug' => $model->url?->slug,
                'dynamic_url' => $model->url && $model->url->domain
                    ? 'https://'.$model->url->domain->name.'/'.$model->url->slug
                    : null,
            ],
            'domains' => $project->domains()->get(['id', 'name']),
            'versions' => Inertia::optional(
                fn () => $model->versions()->with('creator')->orderByDesc('version')->limit(50)->get()->map(fn ($v) => [
                    'version' => $v->version,
                    'name' => $v->name,
                    'type' => $v->type,
                    'is_dynamic' => $v->is_dynamic,
                    'created_at' => $v->created_at->toISOString(),
                    'created_by_name' => $v->creator?->name,
                ])
            ),
        ]);
    }

    public function update(QrCodeRequest $request, string $qrCode)
    {
        $project = $request->get('project');
        $model = $project->qrCodes()->findOrFail($qrCode);
        $data = $request->validated();

        DB::transaction(function () use ($project, $model, $data) {
            $this->persist($project, $model, $data);
            $this->recordVersion($model);
        });

        return redirect()->route('app.project.qrcodes.index')
            ->with('success', 'QR code updated.');
    }

    public function restore(Request $request, string $qrCode, string $version)
    {
        $project = $request->get('project');
        $model = $project->qrCodes()->findOrFail($qrCode);
        $snapshot = $model->versions()->where('version', $version)->firstOrFail();

        $data = [
            'name' => $snapshot->name,
            'type' => $snapshot->type,
            'is_dynamic' => $snapshot->is_dynamic,
            'content' => $snapshot->content,
            'style' => $snapshot->style,
            'domain_id' => $snapshot->domain_id,
            'slug' => $snapshot->slug,
        ];

        // Restoring to dynamic must not collide with another link's slug on that domain.
        if ($snapshot->is_dynamic) {
            $taken = $project->urls()
                ->where('domain_id', $snapshot->domain_id)
                ->where('slug', $snapshot->slug)
                ->when($model->url_id, fn ($q) => $q->where('id', '!=', $model->url_id))
                ->exists();

            if ($taken) {
                return redirect()->back()
                    ->with('error', "That version's short link is already in use. Free up the slug and try again.");
            }
        }

        DB::transaction(function () use ($project, $model, $data) {
            $this->persist($project, $model, $data);
            $this->recordVersion($model);
        });

        return redirect()->route('app.project.qrcodes.index')
            ->with('success', 'QR code restored.');
    }

    public function destroy(Request $request, string $qrCode)
    {
        $project = $request->get('project');
        $model = $project->qrCodes()->findOrFail($qrCode);

        DB::transaction(function () use ($model) {
            $model->url?->delete();
            $model->delete();
        });

        return redirect()->route('app.project.qrcodes.index')
            ->with('success', 'QR code deleted.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Apply a full QR state to the model and sync its backing short link.
     * $data keys: name, type, is_dynamic, content, style, and (when dynamic) domain_id, slug.
     *
     * @param  array<string, mixed>  $data
     */
    private function persist(Project $project, QrCode $model, array $data): void
    {
        if ($data['is_dynamic']) {
            $attrs = [
                'domain_id' => $data['domain_id'],
                'slug' => $data['slug'],
                'url' => $this->backingTarget($project, $data),
                'type' => RedirectType::REDIRECT,
                'status' => UrlStatus::ACTIVATED,
            ];

            if ($model->url_id) {
                $model->url->update($attrs);
            } else {
                // A previously owned soft-deleted URL with the same slug may
                // still occupy the unique index. Restore it instead of inserting.
                $existing = Url::withTrashed()
                    ->where('domain_id', $attrs['domain_id'])
                    ->where('slug', $attrs['slug'])
                    ->first();

                if ($existing && $existing->trashed()) {
                    $existing->restore();
                    $existing->update($attrs);
                    $model->url_id = $existing->id;
                } else {
                    $model->url_id = $project->urls()->create($attrs)->id;
                }
            }
        } elseif ($model->url_id) {
            // Switched dynamic → static: the backing link is no longer needed.
            $model->url->delete();
            $model->url_id = null;
        }

        $model->update([
            'url_id' => $model->url_id,
            'name' => $data['name'],
            'type' => $data['type'],
            'is_dynamic' => $data['is_dynamic'],
            'content' => $data['content'],
            'style' => $data['style'],
        ]);
    }

    /**
     * Append an immutable snapshot of the QR's current persisted state.
     */
    private function recordVersion(QrCode $model): void
    {
        // Force a fresh load: after a dynamic→static transition persist() has
        // soft-deleted the backing Url and set url_id = null, but the in-memory
        // relation cache still holds the trashed Url. loadMissing() would be a
        // no-op on a cached relation, so unset it first to guarantee a clean read.
        $model->unsetRelation('url');
        $model->loadMissing('url');
        $next = (int) $model->versions()->max('version') + 1;

        $model->versions()->create([
            'version' => $next,
            'name' => $model->name,
            'type' => $model->type,
            'is_dynamic' => $model->is_dynamic,
            'content' => $model->content,
            'style' => $model->style,
            // domain_id/slug only exist when there is a (non-deleted) backing link.
            // When url_id is null (static QR), both must be null regardless of cache.
            'domain_id' => $model->url_id ? $model->url?->domain_id : null,
            'slug'      => $model->url_id ? $model->url?->slug : null,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * The URI the backing short link redirects to. vCard QRs are served as a
     * file by RedirectController, so their backing Url just points at its own
     * canonical short URL (the value is never used as a redirect target).
     *
     * @param  array<string, mixed>  $data
     */
    private function backingTarget(Project $project, array $data): string
    {
        if ($data['type'] === 'vcard') {
            // domain_id is validated to exist for this project in QrCodeRequest.
            $domainName = $project->domains()->where('id', $data['domain_id'])->value('name');

            return 'https://'.$domainName.'/'.$data['slug'];
        }

        return QrTarget::redirectTarget($data['type'], $data['content']);
    }
}
