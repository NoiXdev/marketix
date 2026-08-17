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
    // page title refinements
    case TitleBelow30Chars = 'title_below_30_chars';
    case TitleBelow200px = 'title_below_200px';
    case TitleOver561px = 'title_over_561px';
    case TitleSameAsH1 = 'title_same_as_h1';
    case MultipleTitle = 'multiple_title';
    case TitleOutsideHead = 'title_outside_head';
    // meta description refinements
    case MetaDescriptionOver155Chars = 'meta_description_over_155_chars';
    case MetaDescriptionBelow70Chars = 'meta_description_below_70_chars';
    case MetaDescriptionOver985px = 'meta_description_over_985px';
    case MetaDescriptionBelow400px = 'meta_description_below_400px';
    case MultipleMetaDescription = 'multiple_meta_description';
    case MetaDescriptionOutsideHead = 'meta_description_outside_head';
    // meta keywords
    case MissingMetaKeywords = 'missing_meta_keywords';
    case MultipleMetaKeywords = 'multiple_meta_keywords';
    case DuplicateMetaKeywords = 'duplicate_meta_keywords';
    // h1 refinements
    case DuplicateH1 = 'duplicate_h1';
    case H1Over70Chars = 'h1_over_70_chars';
    case AltTextInH1 = 'alt_text_in_h1';
    // h2
    case MissingH2 = 'missing_h2';
    case DuplicateH2 = 'duplicate_h2';
    case H2Over70Chars = 'h2_over_70_chars';
    case MultipleH2 = 'multiple_h2';
    case H2NonSequential = 'h2_non_sequential';
    // canonicals
    case HasCanonical = 'has_canonical';
    case CanonicalSelfReferencing = 'canonical_self_referencing';
    case MissingCanonical = 'missing_canonical';
    case MultipleCanonical = 'multiple_canonical';
    case MultipleConflictingCanonical = 'multiple_conflicting_canonical';
    case NonIndexableCanonical = 'non_indexable_canonical';
    case CanonicalIsRelative = 'canonical_is_relative';
    case CanonicalNotLinked = 'canonical_not_linked';
    case CanonicalInvalidAttribute = 'canonical_invalid_attribute';
    case CanonicalFragmentUrl = 'canonical_fragment_url';
    case CanonicalOutsideHead = 'canonical_outside_head';
    // pagination
    case HasPagination = 'has_pagination';
    case PaginationFirstPage = 'pagination_first_page';
    case Paginated2plus = 'paginated_2plus';
    case PaginationUrlNotInAnchor = 'pagination_url_not_in_anchor';
    case PaginationNon200 = 'pagination_non_200';
    case PaginationUnlinked = 'pagination_unlinked';
    case PaginationNonIndexable = 'pagination_non_indexable';
    case MultiplePaginationUrls = 'multiple_pagination_urls';
    case PaginationLoop = 'pagination_loop';
    case PaginationSequenceError = 'pagination_sequence_error';
    // hreflang
    case HreflangIncorrectCodes = 'hreflang_incorrect_codes';
    case HreflangMultipleEntries = 'hreflang_multiple_entries';
    case HreflangOutsideHead = 'hreflang_outside_head';
    case HreflangMissingSelfReference = 'hreflang_missing_self_reference';
    case HreflangMissingXDefault = 'hreflang_missing_x_default';
    case HreflangNotUsingCanonical = 'hreflang_not_using_canonical';
    case HreflangNon200 = 'hreflang_non_200';
    case HreflangMissingReturnLink = 'hreflang_missing_return_link';
    case HreflangNonCanonicalReturnLink = 'hreflang_non_canonical_return_link';
    case HreflangInconsistentLanguage = 'hreflang_inconsistent_language';
    case HreflangNoindexReturnLink = 'hreflang_noindex_return_link';
    case HreflangUnlinked = 'hreflang_unlinked';
    // links
    case PagesNonCrawlableInternalOutlinks = 'pages_non_crawlable_internal_outlinks';
    case PagesHighCrawlDepth = 'pages_high_crawl_depth';
    case PagesNoInternalOutlinks = 'pages_no_internal_outlinks';
    case InternalNofollowOutlinks = 'internal_nofollow_outlinks';
    case InternalOutlinksNoAnchor = 'internal_outlinks_no_anchor';
    case NonDescriptiveAnchorInternalOutlinks = 'non_descriptive_anchor_internal_outlinks';
    case PagesManyExternalOutlinks = 'pages_many_external_outlinks';
    case PagesManyInternalOutlinks = 'pages_many_internal_outlinks';
    case FollowNofollowInternalInlinks = 'follow_nofollow_internal_inlinks';
    case OnlyInternalNofollowInlinks = 'only_internal_nofollow_inlinks';
    case OutlinksToLocalhost = 'outlinks_to_localhost';
    case OnlyNonIndexableInlinks = 'only_non_indexable_inlinks';
    // content (Tier-A)
    case LoremIpsum = 'lorem_ipsum';
    case ExactDuplicates = 'exact_duplicates';
    // images
    case ImageOver100kb = 'image_over_100kb';
    case ImageMissingSizeAttributes = 'image_missing_size_attributes';
    case ImageMissingAltAttribute = 'image_missing_alt_attribute';
    case ImageAltOver100Chars = 'image_alt_over_100_chars';
    case BackgroundImages = 'background_images';

    public function severity(): string
    {
        return match ($this) {
            self::ServerError, self::ClientError, self::MissingTitle, self::MissingH1,
            self::OversizedResource, self::InternalRedirectLoop, self::InternalNoResponse,
            self::PaginationLoop => 'error',
            self::RedirectChain, self::MultipleH1, self::HeadingOrderSkip, self::Noindex,
            self::CanonicalMismatch, self::RobotsBlocked, self::DuplicateTitle,
            self::DuplicateMetaDescription, self::OrphanPage, self::MissingMetaDescription,
            self::LargeResource, self::BrokenLink,
            self::MissingXFrameOptions, self::MissingXContentTypeOptions, self::MissingHstsHeader,
            self::UnsafeCrossOriginLinks, self::HttpUrls, self::MixedContent,
            self::FormUrlInsecure, self::FormOnHttp, self::WrongContentType,
            self::InternalHttpRefreshRedirect, self::InternalMetaRefreshRedirect,
            self::MultipleTitle, self::TitleOutsideHead, self::MultipleMetaDescription,
            self::MetaDescriptionOutsideHead,
            self::DuplicateH1, self::MultipleH2, self::H2NonSequential,
            self::MultipleCanonical, self::MultipleConflictingCanonical, self::NonIndexableCanonical,
            self::CanonicalInvalidAttribute, self::CanonicalOutsideHead, self::MultiplePaginationUrls,
            self::PaginationUrlNotInAnchor, self::PaginationNon200, self::PaginationUnlinked,
            self::PaginationNonIndexable, self::PaginationSequenceError,
            self::PagesNonCrawlableInternalOutlinks, self::PagesNoInternalOutlinks,
            self::OnlyInternalNofollowInlinks, self::OutlinksToLocalhost,
            self::OnlyNonIndexableInlinks,
            self::LoremIpsum, self::ExactDuplicates,
            self::HreflangIncorrectCodes, self::HreflangMultipleEntries, self::HreflangOutsideHead,
            self::HreflangNotUsingCanonical, self::HreflangNon200, self::HreflangMissingReturnLink,
            self::HreflangNonCanonicalReturnLink, self::HreflangInconsistentLanguage,
            self::HreflangNoindexReturnLink => 'warning',
            self::TitleTooLong, self::ThinContent, self::MissingAltText,
            self::MissingStructuredData, self::NotInSitemap,
            self::MissingCspHeader, self::MissingReferrerPolicy,
            self::ProtocolRelativeResourceLinks,
            self::UrlNonAscii, self::UrlUnderscores, self::UrlUppercase, self::UrlContainsSpace,
            self::UrlMultipleSlashes, self::UrlRepetitivePath, self::UrlGaTrackingParams,
            self::UrlOver115Chars, self::InternalRedirect3xx,
            self::TitleBelow30Chars, self::TitleBelow200px, self::TitleOver561px, self::TitleSameAsH1,
            self::MetaDescriptionOver155Chars, self::MetaDescriptionBelow70Chars,
            self::MetaDescriptionOver985px, self::MetaDescriptionBelow400px,
            self::MissingMetaKeywords, self::MultipleMetaKeywords, self::DuplicateMetaKeywords,
            self::H1Over70Chars, self::AltTextInH1, self::MissingH2, self::DuplicateH2, self::H2Over70Chars,
            self::HasCanonical, self::CanonicalSelfReferencing, self::MissingCanonical,
            self::CanonicalIsRelative, self::CanonicalNotLinked, self::CanonicalFragmentUrl,
            self::HasPagination, self::PaginationFirstPage, self::Paginated2plus,
            self::PagesHighCrawlDepth, self::InternalNofollowOutlinks, self::InternalOutlinksNoAnchor,
            self::NonDescriptiveAnchorInternalOutlinks, self::PagesManyExternalOutlinks,
            self::PagesManyInternalOutlinks, self::FollowNofollowInternalInlinks,
            self::ImageOver100kb, self::ImageMissingSizeAttributes, self::ImageMissingAltAttribute,
            self::ImageAltOver100Chars, self::BackgroundImages,
            self::HreflangMissingSelfReference, self::HreflangMissingXDefault,
            self::HreflangUnlinked => 'notice',
            self::HttpsUrls => 'info',
        };
    }

    public function category(): IssueCategory
    {
        return match ($this) {
            self::ClientError, self::ServerError, self::RedirectChain, self::RobotsBlocked,
            self::InternalRedirect3xx, self::InternalHttpRefreshRedirect, self::InternalMetaRefreshRedirect,
            self::InternalRedirectLoop, self::InternalNoResponse => IssueCategory::ResponseCodes,
            self::MissingTitle, self::DuplicateTitle, self::TitleTooLong,
            self::TitleBelow30Chars, self::TitleBelow200px, self::TitleOver561px, self::TitleSameAsH1,
            self::MultipleTitle, self::TitleOutsideHead => IssueCategory::PageTitle,
            self::MissingMetaDescription, self::DuplicateMetaDescription,
            self::MetaDescriptionOver155Chars, self::MetaDescriptionBelow70Chars,
            self::MetaDescriptionOver985px, self::MetaDescriptionBelow400px,
            self::MultipleMetaDescription, self::MetaDescriptionOutsideHead => IssueCategory::MetaDescription,
            self::MissingMetaKeywords, self::MultipleMetaKeywords, self::DuplicateMetaKeywords => IssueCategory::MetaKeywords,
            self::MissingH1, self::MultipleH1, self::HeadingOrderSkip,
            self::DuplicateH1, self::H1Over70Chars, self::AltTextInH1 => IssueCategory::H1,
            self::MissingH2, self::DuplicateH2, self::H2Over70Chars,
            self::MultipleH2, self::H2NonSequential => IssueCategory::H2,
            self::ThinContent, self::LoremIpsum, self::ExactDuplicates => IssueCategory::Content,
            self::MissingAltText, self::ImageOver100kb, self::ImageMissingSizeAttributes,
            self::ImageMissingAltAttribute, self::ImageAltOver100Chars,
            self::BackgroundImages => IssueCategory::Images,
            self::CanonicalMismatch, self::HasCanonical, self::CanonicalSelfReferencing,
            self::MissingCanonical, self::MultipleCanonical, self::MultipleConflictingCanonical,
            self::NonIndexableCanonical, self::CanonicalIsRelative, self::CanonicalNotLinked,
            self::CanonicalInvalidAttribute, self::CanonicalFragmentUrl,
            self::CanonicalOutsideHead => IssueCategory::Canonicals,
            self::HasPagination, self::PaginationFirstPage, self::Paginated2plus,
            self::PaginationUrlNotInAnchor, self::PaginationNon200, self::PaginationUnlinked,
            self::PaginationNonIndexable, self::MultiplePaginationUrls, self::PaginationLoop,
            self::PaginationSequenceError => IssueCategory::Pagination,
            self::HreflangIncorrectCodes, self::HreflangMultipleEntries, self::HreflangOutsideHead,
            self::HreflangMissingSelfReference, self::HreflangMissingXDefault, self::HreflangNotUsingCanonical,
            self::HreflangNon200, self::HreflangMissingReturnLink, self::HreflangNonCanonicalReturnLink,
            self::HreflangInconsistentLanguage, self::HreflangNoindexReturnLink,
            self::HreflangUnlinked => IssueCategory::Hreflang,
            self::OrphanPage, self::BrokenLink,
            self::PagesNonCrawlableInternalOutlinks, self::PagesHighCrawlDepth,
            self::PagesNoInternalOutlinks, self::InternalNofollowOutlinks,
            self::InternalOutlinksNoAnchor, self::NonDescriptiveAnchorInternalOutlinks,
            self::PagesManyExternalOutlinks, self::PagesManyInternalOutlinks,
            self::FollowNofollowInternalInlinks, self::OnlyInternalNofollowInlinks,
            self::OutlinksToLocalhost, self::OnlyNonIndexableInlinks => IssueCategory::Links,
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
