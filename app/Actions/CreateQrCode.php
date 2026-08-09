<?php

namespace App\Actions;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Http\Controllers\Concerns\InteractsWithUrlSettings;
use App\Models\Project;
use App\Models\QrCode;
use App\Models\User;
use App\Support\QrTarget;
use Illuminate\Support\Facades\DB;

class CreateQrCode
{
    use InteractsWithUrlSettings;

    // ── Default style applied when the caller doesn't supply one ──────────
    // Mirrors QrCodeController::$defaultStyle (the v2 styling shape). The
    // HTTP path always sends a validated 'style', so this only matters for
    // non-HTTP callers (e.g. MCP tools) that omit it.
    private const DEFAULT_STYLE = [
        'foreground' => '#000000',
        'background' => '#ffffff',
        'module_mode' => 'square',
        'module_rounding' => 0,
        'eye_frame_mode' => 'square',
        'eye_frame_rounding' => 0,
        'eye_ball_mode' => 'square',
        'eye_ball_rounding' => 0,
        'error_correction' => 'Q',
        'quiet_zone' => 4,
        'logo_type' => 'none',
        'logo_name' => '',
        'logo_data' => '',
        'logo_size' => 30,
        'logo_margin' => 2,
        'logo_clear_modules' => true,
        'frame_style' => 'none',
        'frame_text' => '',
        'frame_text_color' => '#000000',
        'frame_color' => '#000000',
        'frame_background' => '#ffffff',
    ];

    public function __construct(private CreateLink $createLink) {}

    /**
     * Persist a new QrCode under the given project, creating or reusing its
     * backing Url when dynamic, and record the initial version snapshot.
     *
     * Mirrors QrCodeController@store's creation logic exactly:
     *  - static (is_dynamic = false): no backing Url.
     *  - dynamic + url_id present: attach mode — reuse the existing project
     *    Url, layering the link settings from $data onto it.
     *  - dynamic + no url_id: create a new backing Url via CreateLink (single-
     *    sourced with the standalone Links form), using domain_id/slug and a
     *    computed redirect target.
     *
     * `user_id` is set explicitly on the QrCode's backing Url via CreateLink
     * (rather than relying on the UrlObserver's auth()-based default), since
     * callers such as MCP tools act on behalf of a specific user outside the
     * web auth context. This is a no-op for the HTTP path, where $user is
     * already the authenticated user.
     *
     * Owns creation only — no HTTP concerns (validation, redirect, flash)
     * live here; callers (HTTP controller, MCP tools) own those.
     *
     * @param  array<string, mixed>  $data  Same shape as QrCodeRequest::validated()
     *                                      (name, type, is_dynamic, content, style, and — when dynamic — either
     *                                      url_id, or domain_id/slug plus the link settings: status, password,
     *                                      expired_at, targeting_geo, targeting_device, targeting_language,
     *                                      targeting_ab), plus an optional 'pixel_ids' (array<int, string>) to
     *                                      sync onto the backing Url.
     */
    public function handle(Project $project, User $user, array $data): QrCode
    {
        return DB::transaction(function () use ($project, $user, $data) {
            $pixelIds = $data['pixel_ids'] ?? [];
            $urlId = null;

            if ($data['is_dynamic'] ?? false) {
                if (! empty($data['url_id'])) {
                    // Attach mode (editable): apply settings to the shared link.
                    $url = $project->urls()->findOrFail($data['url_id']);
                    $url->update($this->linkSettingAttributes($data));
                    $this->syncUrlPixels($url, $pixelIds);
                    $urlId = $url->id;
                } else {
                    $url = $this->createLink->handle($project, $user, array_merge([
                        'domain_id' => $data['domain_id'],
                        'slug' => $data['slug'],
                        'url' => $this->backingTarget($project, $data),
                        'type' => RedirectType::REDIRECT,
                        'status' => UrlStatus::ACTIVATED,
                    ], $this->linkSettingAttributes($data)));
                    $this->syncUrlPixels($url, $pixelIds);
                    $urlId = $url->id;
                }
            }

            $qr = $project->qrCodes()->create([
                'url_id' => $urlId,
                'name' => $data['name'],
                'type' => $data['type'],
                'is_dynamic' => $data['is_dynamic'],
                'content' => $data['content'],
                'style' => $data['style'] ?? self::DEFAULT_STYLE,
            ]);

            $this->recordVersion($qr, $user);

            return $qr;
        });
    }

    /**
     * Append an immutable snapshot of the QR's just-persisted state.
     * Mirrors QrCodeController::recordVersion(), but attributes the snapshot
     * to the explicit $user rather than Auth::id(), so it works the same
     * outside the web auth context (e.g. MCP tools).
     */
    private function recordVersion(QrCode $model, User $user): void
    {
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
            'domain_id' => $model->url_id ? $model->url?->domain_id : null,
            'slug' => $model->url_id ? $model->url?->slug : null,
            'created_by' => $user->id,
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
