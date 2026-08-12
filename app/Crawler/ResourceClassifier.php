<?php

namespace App\Crawler;

/**
 * Classifies a crawled resource by its Content-Type and decides, for non-HTML
 * files, whether they are too large for the web. HTML pages get the full SEO
 * analyzer pipeline; everything else (images, PDFs, media, …) is recorded as a
 * file with size metadata instead of being (nonsensically) checked for titles,
 * H1s, structured data, etc.
 */
class ResourceClassifier
{
    public const HTML = 'html';

    public const IMAGE = 'image';

    public const PDF = 'pdf';

    public const MEDIA = 'media';

    public const OTHER = 'other';

    /** Image: getting big > 300 KB, too large > 1 MB. */
    private const IMAGE_WARN = 307_200;

    private const IMAGE_ERROR = 1_048_576;

    /** Other non-HTML assets (PDF, media, fonts, …): getting big > 2 MB. */
    private const OTHER_WARN = 2_097_152;

    public static function categorize(?string $contentType): string
    {
        $type = strtolower(trim(explode(';', (string) $contentType)[0]));

        return match (true) {
            $type === 'text/html', $type === 'application/xhtml+xml' => self::HTML,
            str_starts_with($type, 'image/') => self::IMAGE,
            $type === 'application/pdf' => self::PDF,
            str_starts_with($type, 'video/'), str_starts_with($type, 'audio/') => self::MEDIA,
            default => self::OTHER,
        };
    }

    public static function isHtml(?string $contentType): bool
    {
        return self::categorize($contentType) === self::HTML;
    }

    /**
     * The oversize issue for a non-HTML resource of the given category, or null
     * if it is within acceptable web limits. HTML is never flagged here.
     */
    public static function sizeIssue(string $category, int $sizeBytes): ?IssueCode
    {
        return match ($category) {
            self::HTML => null,
            self::IMAGE => match (true) {
                $sizeBytes > self::IMAGE_ERROR => IssueCode::OversizedResource,
                $sizeBytes > self::IMAGE_WARN => IssueCode::LargeResource,
                default => null,
            },
            default => $sizeBytes > self::OTHER_WARN ? IssueCode::LargeResource : null,
        };
    }
}
