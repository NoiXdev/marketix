<?php

namespace App\Reports;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves the 3 built-in ReportType implementations through the container
 * (so their aggregator dependencies are injected) and indexes them by key.
 * Registered as a singleton in AppServiceProvider.
 */
final class ReportTypeRegistry
{
    /** @var array<string, ReportType> */
    private readonly array $types;

    /** @var list<class-string<ReportType>> */
    private const TYPES = [
        ProjectSummaryReport::class,
        LinkReport::class,
        SiteAnalyticsReport::class,
    ];

    public function __construct(Container $container)
    {
        $types = [];

        foreach (self::TYPES as $class) {
            /** @var ReportType $instance */
            $instance = $container->make($class);
            $types[$instance->key()] = $instance;
        }

        $this->types = $types;
    }

    public function for(string $key): ReportType
    {
        return $this->types[$key] ?? throw new InvalidArgumentException("Unknown report type: {$key}");
    }

    /** @return array<string, ReportType> */
    public function all(): array
    {
        return $this->types;
    }
}
