<?php

namespace App\Crawler;

final class LinkChecks
{
    public const HIGH_CRAWL_DEPTH = 4;

    public const MANY_OUTLINKS = 100;

    /** Anchor texts too generic to describe their target. */
    public const NON_DESCRIPTIVE_ANCHORS = [
        'click here', 'here', 'read more', 'more', 'this', 'link', 'click',
        'learn more', 'details', 'continue', 'read', 'info', 'this page', 'go',
    ];

    public static function isNonDescriptive(string $anchor): bool
    {
        return in_array(mb_strtolower(trim($anchor)), self::NON_DESCRIPTIVE_ANCHORS, true);
    }

    public static function isLocalhost(string $url): bool
    {
        $host = trim(strtolower(parse_url($url, PHP_URL_HOST) ?? ''), '[]');

        return in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)
            || str_ends_with($host, '.localhost');
    }

    /**
     * @param  array{internal:int,external:int,nofollow_internal:bool,no_anchor_internal:bool,non_descriptive_internal:bool,localhost:bool,non_crawlable_internal:bool}  $stats
     * @return string[]
     */
    public static function outlinkIssues(array $stats, ?int $depth): array
    {
        $issues = [];
        if ($depth !== null && $depth >= self::HIGH_CRAWL_DEPTH) {
            $issues[] = IssueCode::PagesHighCrawlDepth->value;
        }
        if ($stats['internal'] === 0) {
            $issues[] = IssueCode::PagesNoInternalOutlinks->value;
        }
        if ($stats['internal'] > self::MANY_OUTLINKS) {
            $issues[] = IssueCode::PagesManyInternalOutlinks->value;
        }
        if ($stats['external'] > self::MANY_OUTLINKS) {
            $issues[] = IssueCode::PagesManyExternalOutlinks->value;
        }
        if ($stats['nofollow_internal']) {
            $issues[] = IssueCode::InternalNofollowOutlinks->value;
        }
        if ($stats['no_anchor_internal']) {
            $issues[] = IssueCode::InternalOutlinksNoAnchor->value;
        }
        if ($stats['non_descriptive_internal']) {
            $issues[] = IssueCode::NonDescriptiveAnchorInternalOutlinks->value;
        }
        if ($stats['localhost']) {
            $issues[] = IssueCode::OutlinksToLocalhost->value;
        }
        if ($stats['non_crawlable_internal']) {
            $issues[] = IssueCode::PagesNonCrawlableInternalOutlinks->value;
        }

        return $issues;
    }

    /**
     * @param  array{count:int,follow:bool,nofollow:bool,anyIndexableSource:bool}  $detail
     * @return string[]
     */
    public static function inlinkIssues(array $detail): array
    {
        if ($detail['count'] === 0) {
            return [];
        }
        $issues = [];
        if ($detail['follow'] && $detail['nofollow']) {
            $issues[] = IssueCode::FollowNofollowInternalInlinks->value;
        }
        if ($detail['nofollow'] && ! $detail['follow']) {
            $issues[] = IssueCode::OnlyInternalNofollowInlinks->value;
        }
        if (! $detail['anyIndexableSource']) {
            $issues[] = IssueCode::OnlyNonIndexableInlinks->value;
        }

        return $issues;
    }
}
