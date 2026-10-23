<?php

namespace App\Actions;

use App\Models\Annotation;
use App\Models\Domain;
use App\Models\Event;
use App\Models\Funnel;
use App\Models\Goal;
use App\Models\PageView;
use App\Models\Pixel;
use App\Models\Project;
use App\Models\QrCode;
use App\Models\QrCodeVersion;
use App\Models\QrTemplate;
use App\Models\ScheduledReport;
use App\Models\Site;
use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
use App\Models\Visit;
use App\Support\ActivityRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MoveToProject
{
    public function url(Url $url, Project $target, Domain $domain, User $causer): void
    {
        $source = $url->project;

        DB::transaction(function () use ($url, $source, $target, $domain, $causer) {
            $this->moveUrls(collect([$url->id]), $target, $domain->id);
            $this->log($url, 'url', $source, $target, $causer);
        });
    }

    public function qrCode(QrCode $qrCode, Project $target, ?Domain $domain, User $causer): void
    {
        $source = $qrCode->project;

        DB::transaction(function () use ($qrCode, $source, $target, $domain, $causer) {
            if ($qrCode->url_id) {
                $this->moveUrls(collect([$qrCode->url_id]), $target, $domain->id);
            } else {
                $qrCode->update(['project_id' => $target->id]);
            }

            $this->log($qrCode, 'qrcode', $source, $target, $causer);
        });
    }

    public function domain(Domain $domain, Project $target, User $causer): void
    {
        $source = $domain->project;

        DB::transaction(function () use ($domain, $source, $target, $causer) {
            $domain->update(['project_id' => $target->id]);
            $this->moveUrls(Url::withTrashed()->where('domain_id', $domain->id)->pluck('id'), $target, $domain->id);
            $this->log($domain, 'domain', $source, $target, $causer);
        });
    }

    public function site(Site $site, Project $target, User $causer): void
    {
        $source = $site->project;

        DB::transaction(function () use ($site, $source, $target, $causer) {
            $site->update(['project_id' => $target->id]);

            foreach ([Goal::withTrashed(), Funnel::withTrashed(), Annotation::withTrashed(), Visit::query(), PageView::query(), Event::query()] as $query) {
                $query->where('site_id', $site->id)->toBase()->update(['project_id' => $target->id]);
            }

            ScheduledReport::where('type', 'site_analytics')
                ->where('subject_id', $site->id)
                ->update(['project_id' => $target->id]);

            $this->log($site, 'site', $source, $target, $causer);
        });
    }

    public function pixel(Pixel $pixel, Project $target, User $causer): void
    {
        $source = $pixel->project;

        DB::transaction(function () use ($pixel, $source, $target, $causer) {
            $pixel->urls()->detach();
            $pixel->update(['project_id' => $target->id]);
            $this->log($pixel, 'pixel', $source, $target, $causer);
        });
    }

    public function qrTemplate(QrTemplate $template, Project $target): void
    {
        $template->update(['project_id' => $target->id]);
    }

    /**
     * Pixels belong to the source project, so links leave them behind. QR code
     * versions are re-pointed at the new domain so restoring an old version
     * cannot reach back into a domain of the source project.
     *
     * @param  Collection<int, string>  $ids
     */
    private function moveUrls(Collection $ids, Project $target, string $domainId): void
    {
        foreach ($ids->chunk(500) as $chunk) {
            DB::table('pixel_url')->whereIn('url_id', $chunk)->delete();

            Url::withTrashed()->whereIn('id', $chunk)->update(['project_id' => $target->id, 'domain_id' => $domainId]);
            Statistic::withTrashed()->whereIn('url_id', $chunk)->toBase()->update(['project_id' => $target->id]);
            ScheduledReport::where('type', 'link')->whereIn('subject_id', $chunk)->update(['project_id' => $target->id]);

            $qrCodeIds = QrCode::withTrashed()->whereIn('url_id', $chunk)->pluck('id');
            QrCode::withTrashed()->whereIn('id', $qrCodeIds)->update(['project_id' => $target->id]);
            QrCodeVersion::whereIn('qr_code_id', $qrCodeIds)->whereNotNull('domain_id')->toBase()->update(['domain_id' => $domainId]);
        }
    }

    private function log(Model $subject, string $logName, Project $source, Project $target, User $causer): void
    {
        ActivityRecorder::project($logName, 'moved_out', $source->id, $causer, $subject, ['project' => $target->name]);
        ActivityRecorder::project($logName, 'moved_in', $target->id, $causer, $subject, ['project' => $source->name]);
    }
}
