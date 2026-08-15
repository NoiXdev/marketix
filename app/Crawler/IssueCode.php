<?php

namespace App\Crawler;

enum IssueCode: string
{
    // status / links
    case ClientError = 'client_error';        // 4xx
    case ServerError = 'server_error';         // 5xx
    case RedirectChain = 'redirect_chain';     // >1 hop
    case OrphanPage = 'orphan_page';           // 0 inlinks
    case BrokenLink = 'broken_link';           // links to a URL that returns 4xx/5xx
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
    // resources (non-HTML files: images, media, …)
    case LargeResource = 'large_resource';       // over the "getting big" threshold
    case OversizedResource = 'oversized_resource'; // clearly too large for the web
    // security
    case MissingCspHeader = 'missing_csp_header';
    case MissingXFrameOptions = 'missing_x_frame_options';
    case MissingXContentTypeOptions = 'missing_x_content_type_options';
    case MissingHstsHeader = 'missing_hsts_header';
    case UnsafeCrossOriginLinks = 'unsafe_cross_origin_links';
    case MissingReferrerPolicy = 'missing_referrer_policy';
    case HttpUrls = 'http_urls';
    case HttpsUrls = 'https_urls';
    case MixedContent = 'mixed_content';
    case FormUrlInsecure = 'form_url_insecure';
    case FormOnHttp = 'form_on_http';
    case ProtocolRelativeResourceLinks = 'protocol_relative_resource_links';
    case WrongContentType = 'wrong_content_type';
    // url
    case UrlNonAscii = 'url_non_ascii';
    case UrlUnderscores = 'url_underscores';
    case UrlUppercase = 'url_uppercase';
    case UrlContainsSpace = 'url_contains_space';
    case UrlMultipleSlashes = 'url_multiple_slashes';
    case UrlRepetitivePath = 'url_repetitive_path';
    case UrlGaTrackingParams = 'url_ga_tracking_params';
    case UrlOver115Chars = 'url_over_115_chars';
    // response code refinements
    case InternalRedirect3xx = 'internal_redirect_3xx';
    case InternalHttpRefreshRedirect = 'internal_http_refresh_redirect';
    case InternalMetaRefreshRedirect = 'internal_meta_refresh_redirect';
    case InternalRedirectLoop = 'internal_redirect_loop';
    case InternalNoResponse = 'internal_no_response';

    public function severity(): string
    {
        return match ($this) {
            self::ServerError, self::ClientError, self::MissingTitle, self::MissingH1,
            self::OversizedResource, self::InternalRedirectLoop, self::InternalNoResponse => 'error',
            self::RedirectChain, self::MultipleH1, self::HeadingOrderSkip, self::Noindex,
            self::CanonicalMismatch, self::RobotsBlocked, self::DuplicateTitle,
            self::DuplicateMetaDescription, self::OrphanPage, self::MissingMetaDescription,
            self::LargeResource, self::BrokenLink,
            self::MissingXFrameOptions, self::MissingXContentTypeOptions, self::MissingHstsHeader,
            self::UnsafeCrossOriginLinks, self::HttpUrls, self::MixedContent,
            self::FormUrlInsecure, self::FormOnHttp, self::WrongContentType,
            self::InternalHttpRefreshRedirect, self::InternalMetaRefreshRedirect => 'warning',
            self::TitleTooLong, self::ThinContent, self::MissingAltText,
            self::MissingStructuredData, self::NotInSitemap,
            self::MissingCspHeader, self::MissingReferrerPolicy,
            self::ProtocolRelativeResourceLinks,
            self::UrlNonAscii, self::UrlUnderscores, self::UrlUppercase, self::UrlContainsSpace,
            self::UrlMultipleSlashes, self::UrlRepetitivePath, self::UrlGaTrackingParams,
            self::UrlOver115Chars, self::InternalRedirect3xx => 'notice',
            self::HttpsUrls => 'info',
        };
    }

    public function category(): IssueCategory
    {
        return match ($this) {
            self::ClientError, self::ServerError, self::RedirectChain, self::RobotsBlocked,
            self::InternalRedirect3xx, self::InternalHttpRefreshRedirect, self::InternalMetaRefreshRedirect,
            self::InternalRedirectLoop, self::InternalNoResponse => IssueCategory::ResponseCodes,
            self::MissingTitle, self::DuplicateTitle, self::TitleTooLong => IssueCategory::PageTitle,
            self::MissingMetaDescription, self::DuplicateMetaDescription => IssueCategory::MetaDescription,
            self::MissingH1, self::MultipleH1, self::HeadingOrderSkip => IssueCategory::H1,
            self::ThinContent => IssueCategory::Content,
            self::MissingAltText => IssueCategory::Images,
            self::CanonicalMismatch => IssueCategory::Canonicals,
            self::OrphanPage, self::BrokenLink => IssueCategory::Links,
            self::MissingCspHeader, self::MissingXFrameOptions, self::MissingXContentTypeOptions,
            self::MissingHstsHeader, self::UnsafeCrossOriginLinks, self::MissingReferrerPolicy,
            self::HttpUrls, self::HttpsUrls, self::MixedContent, self::FormUrlInsecure,
            self::FormOnHttp, self::ProtocolRelativeResourceLinks, self::WrongContentType => IssueCategory::Security,
            self::UrlNonAscii, self::UrlUnderscores, self::UrlUppercase, self::UrlContainsSpace,
            self::UrlMultipleSlashes, self::UrlRepetitivePath, self::UrlGaTrackingParams,
            self::UrlOver115Chars => IssueCategory::Url,
            self::Noindex, self::MissingStructuredData, self::NotInSitemap,
            self::LargeResource, self::OversizedResource => IssueCategory::Other,
        };
    }

    public function label(): string
    {
        return __('crawler.issue.'.$this->value);
    }
}
