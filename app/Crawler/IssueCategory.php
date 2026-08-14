<?php

namespace App\Crawler;

enum IssueCategory: string
{
    case Security = 'security';
    case ResponseCodes = 'response_codes';
    case Url = 'url';
    case PageTitle = 'page_title';
    case MetaDescription = 'meta_description';
    case MetaKeywords = 'meta_keywords';
    case H1 = 'h1';
    case H2 = 'h2';
    case Content = 'content';
    case Images = 'images';
    case Canonicals = 'canonicals';
    case Pagination = 'pagination';
    case Links = 'links';
    case Other = 'other';

    public function label(): string
    {
        return __('crawler.category_group.'.$this->value);
    }
}
