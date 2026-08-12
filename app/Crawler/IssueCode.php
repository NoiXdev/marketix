<?php
// app/Crawler/IssueCode.php
namespace App\Crawler;

enum IssueCode: string
{
    // status / links
    case ClientError = 'client_error';        // 4xx
    case ServerError = 'server_error';         // 5xx
    case RedirectChain = 'redirect_chain';     // >1 hop
    case OrphanPage = 'orphan_page';           // 0 inlinks
    // on-page / meta
    case MissingTitle = 'missing_title';
    case DuplicateTitle = 'duplicate_title';
    case TitleTooLong = 'title_too_long';
    case MissingMetaDescription = 'missing_meta_description';
    case DuplicateMetaDescription = 'duplicate_meta_description';
    case ThinContent = 'thin_content';
    // headings
    case MissingH1 = 'missing_h1';
    case MultipleH1 = 'multiple_h1';
    case HeadingOrderSkip = 'heading_order_skip';
    // indexability
    case Noindex = 'noindex';
    case CanonicalMismatch = 'canonical_mismatch';
    case RobotsBlocked = 'robots_blocked';
    // technik
    case MissingAltText = 'missing_alt_text';
    case MissingStructuredData = 'missing_structured_data';
    // sitemap
    case NotInSitemap = 'not_in_sitemap';

    public function severity(): string
    {
        return match ($this) {
            self::ServerError, self::ClientError, self::MissingTitle, self::MissingH1 => 'error',
            self::RedirectChain, self::MultipleH1, self::HeadingOrderSkip, self::Noindex,
            self::CanonicalMismatch, self::RobotsBlocked, self::DuplicateTitle,
            self::DuplicateMetaDescription, self::OrphanPage, self::MissingMetaDescription => 'warning',
            self::TitleTooLong, self::ThinContent, self::MissingAltText,
            self::MissingStructuredData, self::NotInSitemap => 'notice',
        };
    }

    public function label(): string
    {
        return __('crawler.issue.'.$this->value);
    }
}
