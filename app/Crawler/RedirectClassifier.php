<?php

namespace App\Crawler;

class RedirectClassifier
{
    /**
     * Issues for a successful (crawled) response.
     *
     * @param  string[]  $chain  [$url, ...redirect targets], or [] when there was no redirect
     * @param  array<string, string[]>  $lowerHeaders  headers with lower-cased keys
     * @return string[] IssueCode values
     */
    public static function issues(array $chain, array $lowerHeaders, string $body, bool $isHtml): array
    {
        $issues = [];
        $hops = max(0, count($chain) - 1);

        if ($hops >= 2) {
            $issues[] = IssueCode::RedirectChain->value;
        } elseif ($hops === 1) {
            $issues[] = IssueCode::InternalRedirect3xx->value;
        }

        if (count(array_unique($chain)) !== count($chain)) {
            $issues[] = IssueCode::InternalRedirectLoop->value;
        }

        if (isset($lowerHeaders['refresh'])) {
            $issues[] = IssueCode::InternalHttpRefreshRedirect->value;
        }

        if ($isHtml && preg_match('/<meta[^>]+http-equiv\s*=\s*["\']?\s*refresh/i', $body)) {
            $issues[] = IssueCode::InternalMetaRefreshRedirect->value;
        }

        return $issues;
    }

    /**
     * Issues for a failed fetch (crawlFailed). Mutually exclusive, in precedence order.
     *
     * @return string[] IssueCode values
     */
    public static function failureIssues(?int $status, bool $tooManyRedirects): array
    {
        if ($tooManyRedirects) {
            return [IssueCode::InternalRedirectLoop->value];
        }
        if ($status === null) {
            return [IssueCode::InternalNoResponse->value];
        }
        if ($status >= 400 && $status < 500) {
            return [IssueCode::ClientError->value];
        }

        return [IssueCode::ServerError->value];
    }
}
