<?php

namespace App\Http\Controllers;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Http\Requests\QrCodeRequest;
use App\Models\Project;
use App\Support\QrTarget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

            $project->qrCodes()->create([
                'url_id' => $urlId,
                'name' => $data['name'],
                'type' => $data['type'],
                'is_dynamic' => $data['is_dynamic'],
                'content' => $data['content'],
                'style' => $data['style'],
            ]);
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
        ]);
    }

    public function update(QrCodeRequest $request, string $qrCode)
    {
        $project = $request->get('project');
        $model = $project->qrCodes()->findOrFail($qrCode);
        $data = $request->validated();

        DB::transaction(function () use ($project, $model, $data) {
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
                    $model->url_id = $project->urls()->create($attrs)->id;
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
        });

        return redirect()->route('app.project.qrcodes.index')
            ->with('success', 'QR code updated.');
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
