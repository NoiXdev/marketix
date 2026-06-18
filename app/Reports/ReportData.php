<?php

namespace App\Reports;

class ReportData
{
    /**
     * @param  list<array{date:string,clicks:int,unique:int}>  $timeSeries
     * @param  array<string,list<array{label:string,count:int}>>  $breakdowns
     * @param  list<array{slug:string,domain:string,clicks:int}>  $topLinks
     * @param  list<array{country:?string,city:?string,browser:?string,os:?string,domain:?string,created_at:string}>  $recentClicks
     */
    public function __construct(
        public readonly string $scope,
        public readonly string $title,
        public readonly string $subtitle,
        public readonly string $rangeLabel,
        public readonly string $generatedAt,
        public readonly int $totalClicks,
        public readonly int $uniqueClicks,
        public readonly array $timeSeries,
        public readonly array $breakdowns,
        public readonly array $topLinks = [],
        public readonly array $recentClicks = [],
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
