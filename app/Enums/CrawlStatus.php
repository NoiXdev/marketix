<?php

namespace App\Enums;

enum CrawlStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => __('crawler.status_queued'),
            self::Running => __('crawler.status_running'),
            self::Completed => __('crawler.status_completed'),
            self::Failed => __('crawler.status_failed'),
        };
    }
}
