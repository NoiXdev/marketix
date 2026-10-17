<?php

namespace App\Support\Analytics;

use App\Models\Event;
use App\Models\PageView;
use App\Models\Site;
use App\Models\Visit;
use App\Support\VisitorHash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Everything stored about one visitor recognised by the snippet's visitor id
 * (cookie and hybrid mode), for access and erasure requests.
 */
final class VisitorData
{
    private const INTERNAL = ['id', 'visit_id', 'site_id', 'project_id', 'visitor_hash'];

    private function __construct(private Site $site, private string $visitorHash) {}

    public static function for(Site $site, string $visitorId): self
    {
        return new self($site, VisitorHash::forVisitorId($visitorId, $site->id));
    }

    /** @return array{visits: int, page_views: int, events: int} */
    public function counts(): array
    {
        return [
            'visits' => $this->query(Visit::class)->count(),
            'page_views' => $this->query(PageView::class)->count(),
            'events' => $this->query(Event::class)->count(),
        ];
    }

    /** @return array{site: string, visits: list<array<string, mixed>>, page_views: list<array<string, mixed>>, events: list<array<string, mixed>>} */
    public function export(): array
    {
        return [
            'site' => $this->site->domain,
            'visits' => $this->rows(Visit::class, 'started_at'),
            'page_views' => $this->rows(PageView::class, 'created_at'),
            'events' => $this->rows(Event::class, 'created_at'),
        ];
    }

    /** @return array{visits: int, page_views: int, events: int} */
    public function forget(): array
    {
        return DB::transaction(fn () => [
            'events' => $this->query(Event::class)->delete(),
            'page_views' => $this->query(PageView::class)->delete(),
            'visits' => $this->query(Visit::class)->delete(),
        ]);
    }

    /**
     * @param  class-string<Model>  $model
     * @return list<array<string, mixed>>
     */
    private function rows(string $model, string $order): array
    {
        return $this->query($model)->orderBy($order)->get()
            ->map(fn (Model $row) => Arr::except($row->toArray(), self::INTERNAL))
            ->values()
            ->all();
    }

    /**
     * @param  class-string<Model>  $model
     * @return Builder<Model>
     */
    private function query(string $model): Builder
    {
        return $model::query()->where('site_id', $this->site->id)->where('visitor_hash', $this->visitorHash);
    }
}
