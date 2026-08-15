<?php

namespace App\Crawler;

/**
 * The single source of truth for every crawler check: its category, severity and
 * whether it is implemented yet (active) or catalogued for a later phase (planned).
 * Active codes are real IssueCode cases the crawler emits; planned codes are shown
 * greyed in the UI and never emitted until implemented.
 */
class CheckCatalog
{
    private const A = 'active';

    private const P = 'planned';

    /** @return array<int, array{code: string, category: string, severity: string, status: string}> */
    public static function all(): array
    {
        return [
            // security
            ['code' => 'missing_csp_header', 'category' => 'security', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'missing_x_frame_options', 'category' => 'security', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'missing_x_content_type_options', 'category' => 'security', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'missing_hsts_header', 'category' => 'security', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'unsafe_cross_origin_links', 'category' => 'security', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'missing_referrer_policy', 'category' => 'security', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'http_urls', 'category' => 'security', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'https_urls', 'category' => 'security', 'severity' => 'info', 'status' => self::A],
            ['code' => 'mixed_content', 'category' => 'security', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'form_url_insecure', 'category' => 'security', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'form_on_http', 'category' => 'security', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'protocol_relative_resource_links', 'category' => 'security', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'wrong_content_type', 'category' => 'security', 'severity' => 'warning', 'status' => self::A],

            // response_codes
            ['code' => 'robots_blocked', 'category' => 'response_codes', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'client_error', 'category' => 'response_codes', 'severity' => 'error', 'status' => self::A],
            ['code' => 'server_error', 'category' => 'response_codes', 'severity' => 'error', 'status' => self::A],
            ['code' => 'redirect_chain', 'category' => 'response_codes', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'external_server_error_5xx', 'category' => 'response_codes', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'internal_redirect_3xx', 'category' => 'response_codes', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'internal_redirect_loop', 'category' => 'response_codes', 'severity' => 'error', 'status' => self::A],
            ['code' => 'internal_http_refresh_redirect', 'category' => 'response_codes', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'internal_meta_refresh_redirect', 'category' => 'response_codes', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'internal_js_redirect', 'category' => 'response_codes', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'internal_success_2xx', 'category' => 'response_codes', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'internal_no_response', 'category' => 'response_codes', 'severity' => 'error', 'status' => self::A],
            ['code' => 'internal_blocked_resource', 'category' => 'response_codes', 'severity' => 'warning', 'status' => self::P],

            // url (8 active in Phase 2; 3 remain planned)
            ['code' => 'url_non_ascii', 'category' => 'url', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'url_underscores', 'category' => 'url', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'url_uppercase', 'category' => 'url', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'url_multiple_slashes', 'category' => 'url', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'url_repetitive_path', 'category' => 'url', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'url_contains_space', 'category' => 'url', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'url_internal_search', 'category' => 'url', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'url_parameters', 'category' => 'url', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'url_broken_bookmark', 'category' => 'url', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'url_ga_tracking_params', 'category' => 'url', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'url_over_115_chars', 'category' => 'url', 'severity' => 'notice', 'status' => self::A],

            // page_title
            ['code' => 'missing_title', 'category' => 'page_title', 'severity' => 'error', 'status' => self::A],
            ['code' => 'duplicate_title', 'category' => 'page_title', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'title_too_long', 'category' => 'page_title', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'title_below_200px', 'category' => 'page_title', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'title_below_30_chars', 'category' => 'page_title', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'title_over_561px', 'category' => 'page_title', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'title_same_as_h1', 'category' => 'page_title', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'multiple_title', 'category' => 'page_title', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'title_outside_head', 'category' => 'page_title', 'severity' => 'warning', 'status' => self::A],

            // meta_description
            ['code' => 'missing_meta_description', 'category' => 'meta_description', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'duplicate_meta_description', 'category' => 'meta_description', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'meta_description_over_155_chars', 'category' => 'meta_description', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'meta_description_over_985px', 'category' => 'meta_description', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'meta_description_below_70_chars', 'category' => 'meta_description', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'meta_description_below_400px', 'category' => 'meta_description', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'multiple_meta_description', 'category' => 'meta_description', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'meta_description_outside_head', 'category' => 'meta_description', 'severity' => 'warning', 'status' => self::A],

            // meta_keywords
            ['code' => 'missing_meta_keywords', 'category' => 'meta_keywords', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'duplicate_meta_keywords', 'category' => 'meta_keywords', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'multiple_meta_keywords', 'category' => 'meta_keywords', 'severity' => 'notice', 'status' => self::A],

            // h1
            ['code' => 'missing_h1', 'category' => 'h1', 'severity' => 'error', 'status' => self::A],
            ['code' => 'multiple_h1', 'category' => 'h1', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'heading_order_skip', 'category' => 'h1', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'duplicate_h1', 'category' => 'h1', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'h1_over_70_chars', 'category' => 'h1', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'alt_text_in_h1', 'category' => 'h1', 'severity' => 'notice', 'status' => self::A],

            // h2
            ['code' => 'missing_h2', 'category' => 'h2', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'duplicate_h2', 'category' => 'h2', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'h2_over_70_chars', 'category' => 'h2', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'multiple_h2', 'category' => 'h2', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'h2_non_sequential', 'category' => 'h2', 'severity' => 'warning', 'status' => self::A],

            // content (thin_content active; Tier-C permanently planned)
            ['code' => 'thin_content', 'category' => 'content', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'exact_duplicates', 'category' => 'content', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'lorem_ipsum', 'category' => 'content', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'readability_hard', 'category' => 'content', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'readability_very_hard', 'category' => 'content', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'near_duplicates', 'category' => 'content', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'semantically_similar', 'category' => 'content', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'low_relevance', 'category' => 'content', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'soft_404', 'category' => 'content', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'spelling_errors', 'category' => 'content', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'grammar_errors', 'category' => 'content', 'severity' => 'notice', 'status' => self::P],

            // images
            ['code' => 'missing_alt_text', 'category' => 'images', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'image_over_100kb', 'category' => 'images', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'image_missing_size_attributes', 'category' => 'images', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'image_missing_alt_attribute', 'category' => 'images', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'image_alt_over_100_chars', 'category' => 'images', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'background_images', 'category' => 'images', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'incorrectly_sized_images', 'category' => 'images', 'severity' => 'notice', 'status' => self::P],

            // canonicals
            ['code' => 'canonical_mismatch', 'category' => 'canonicals', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'has_canonical', 'category' => 'canonicals', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'canonical_self_referencing', 'category' => 'canonicals', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'missing_canonical', 'category' => 'canonicals', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'multiple_canonical', 'category' => 'canonicals', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'multiple_conflicting_canonical', 'category' => 'canonicals', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'non_indexable_canonical', 'category' => 'canonicals', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'canonical_is_relative', 'category' => 'canonicals', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'canonical_not_linked', 'category' => 'canonicals', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'canonical_invalid_attribute', 'category' => 'canonicals', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'canonical_fragment_url', 'category' => 'canonicals', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'canonical_outside_head', 'category' => 'canonicals', 'severity' => 'warning', 'status' => self::A],

            // pagination
            ['code' => 'has_pagination', 'category' => 'pagination', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'pagination_first_page', 'category' => 'pagination', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'paginated_2plus', 'category' => 'pagination', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'pagination_url_not_in_anchor', 'category' => 'pagination', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'pagination_non_200', 'category' => 'pagination', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'pagination_unlinked', 'category' => 'pagination', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'pagination_non_indexable', 'category' => 'pagination', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'multiple_pagination_urls', 'category' => 'pagination', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'pagination_loop', 'category' => 'pagination', 'severity' => 'error', 'status' => self::A],
            ['code' => 'pagination_sequence_error', 'category' => 'pagination', 'severity' => 'warning', 'status' => self::A],

            // links
            ['code' => 'orphan_page', 'category' => 'links', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'broken_link', 'category' => 'links', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'pages_non_crawlable_internal_outlinks', 'category' => 'links', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'pages_high_crawl_depth', 'category' => 'links', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'pages_no_internal_outlinks', 'category' => 'links', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'internal_nofollow_outlinks', 'category' => 'links', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'internal_outlinks_no_anchor', 'category' => 'links', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'non_descriptive_anchor_internal_outlinks', 'category' => 'links', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'pages_many_external_outlinks', 'category' => 'links', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'pages_many_internal_outlinks', 'category' => 'links', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'follow_nofollow_internal_inlinks', 'category' => 'links', 'severity' => 'notice', 'status' => self::P],
            ['code' => 'only_internal_nofollow_inlinks', 'category' => 'links', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'outlinks_to_localhost', 'category' => 'links', 'severity' => 'warning', 'status' => self::P],
            ['code' => 'only_non_indexable_inlinks', 'category' => 'links', 'severity' => 'warning', 'status' => self::P],

            // other (existing checks outside the 13 user categories)
            ['code' => 'noindex', 'category' => 'other', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'missing_structured_data', 'category' => 'other', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'not_in_sitemap', 'category' => 'other', 'severity' => 'notice', 'status' => self::A],
            ['code' => 'large_resource', 'category' => 'other', 'severity' => 'warning', 'status' => self::A],
            ['code' => 'oversized_resource', 'category' => 'other', 'severity' => 'error', 'status' => self::A],
        ];
    }

    /** @return string[] */
    public static function activeCodes(): array
    {
        return array_values(array_map(
            fn ($e) => $e['code'],
            array_filter(self::all(), fn ($e) => $e['status'] === self::A),
        ));
    }

    /** @return string[] */
    public static function activeCodesForCategory(string $category): array
    {
        return array_values(array_map(
            fn ($e) => $e['code'],
            array_filter(self::all(), fn ($e) => $e['status'] === self::A && $e['category'] === $category),
        ));
    }

    public static function categoryOf(string $code): ?string
    {
        foreach (self::all() as $entry) {
            if ($entry['code'] === $code) {
                return $entry['category'];
            }
        }

        return null;
    }

    /** True unless the code is an informational (`info`) signal that should not count as a problem. */
    public static function isProblemCode(string $code): bool
    {
        foreach (self::all() as $entry) {
            if ($entry['code'] === $code) {
                return $entry['severity'] !== 'info';
            }
        }

        return true;
    }
}
