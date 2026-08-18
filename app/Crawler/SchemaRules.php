<?php

namespace App\Crawler;

/**
 * Curated schema.org required-field map for structured data validation.
 *
 * Conservative on purpose: only lists properties Google's structured data
 * documentation marks as required for rich result eligibility. A token
 * containing "|" means "at least one of these properties must be present"
 * (the analyzer consuming this map is responsible for interpreting that).
 */
class SchemaRules
{
    /** @var array<string, string[]> */
    private const RULES = [
        'Organization' => ['name'],
        'LocalBusiness' => ['name', 'address'],
        'WebSite' => ['name', 'url'],
        'WebPage' => [],
        'Article' => ['headline'],
        'BlogPosting' => ['headline'],
        'NewsArticle' => ['headline'],
        'Product' => ['name'],
        'Offer' => ['price', 'priceCurrency'],
        'AggregateRating' => ['ratingValue'],
        'Review' => ['reviewRating'],
        'BreadcrumbList' => ['itemListElement'],
        'ListItem' => ['position', 'name'],
        'FAQPage' => ['mainEntity'],
        'Question' => ['name', 'acceptedAnswer'],
        'Answer' => ['text'],
        'Event' => ['name', 'startDate'],
        'Recipe' => ['name'],
        'Person' => ['name'],
        'ImageObject' => ['contentUrl|url'],
        'VideoObject' => ['name', 'thumbnailUrl', 'uploadDate'],
    ];

    /**
     * Required property tokens for the given schema.org type.
     *
     * @return string[]
     */
    public static function requiredFor(string $type): array
    {
        return self::RULES[$type] ?? [];
    }
}
