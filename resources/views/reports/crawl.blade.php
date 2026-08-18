<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>@lang('crawler.report_title')</title>
    <style>
        @page { margin: 24mm 16mm; }
        * { font-family: DejaVu Sans, Arial, sans-serif; color: #0f172a; }
        body { font-size: 12px; margin: 0; }
        .cover { page-break-after: always; padding-top: 60mm; text-align: center; }
        .cover .brand { font-size: 28px; font-weight: 700; color: #4f46e5; }
        .cover h1 { font-size: 22px; margin: 16px 0 4px; }
        .cover .meta { color: #64748b; font-size: 13px; }
        h2 { font-size: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 4px; margin-top: 28px; }
        .scores { display: flex; gap: 12px; margin: 16px 0; }
        .score { flex: 1; border-radius: 8px; padding: 16px; text-align: center; }
        .score .value { font-size: 32px; font-weight: 700; }
        .score .label { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        .score-green { background: #dcfce7; color: #166534; }
        .score-amber { background: #fef3c7; color: #92400e; }
        .score-red { background: #fee2e2; color: #991b1b; }
        .categories { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .categories td { padding: 4px 8px; border-bottom: 1px solid #f1f5f9; }
        .categories td.num { text-align: right; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #f1f5f9; }
        th { color: #64748b; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
    </style>
</head>
<body>
    <div class="cover">
        <div class="brand">{{ config('app.name') }}</div>
        <h1>@lang('crawler.report_title')</h1>
        <div class="meta">{{ $project->name }}</div>
        <div class="meta">{{ $crawl->start_url }}</div>
        <div class="meta">{{ $crawl->pages_crawled }} @lang('crawler.report_pages')</div>
        <div class="meta">@lang('crawler.report_generated') {{ now()->format('j M Y, H:i') }}</div>
    </div>

    <h2>@lang('crawler.report_scores')</h2>
    <div class="scores">
        @php
            $band = fn (int $n) => $n >= 80 ? 'score-green' : ($n >= 60 ? 'score-amber' : 'score-red');
        @endphp
        <div class="score {{ $band($score['overall']) }}">
            <div class="value">{{ $score['overall'] }}</div>
            <div class="label">@lang('crawler.score_overall')</div>
        </div>
        <div class="score {{ $band($score['seo']) }}">
            <div class="value">{{ $score['seo'] }}</div>
            <div class="label">@lang('crawler.score_seo')</div>
        </div>
        <div class="score {{ $band($score['geo']) }}">
            <div class="value">{{ $score['geo'] }}</div>
            <div class="label">@lang('crawler.score_geo')</div>
        </div>
    </div>

    <table class="categories">
        <tbody>
        @foreach ($score['categories'] as $cat => $value)
            <tr>
                <td>@lang('crawler.category_group.'.$cat)</td>
                <td class="num">{{ $value }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h2>@lang('crawler.report_top_actions')</h2>
    @if (empty($score['topActions']))
        <p>@lang('crawler.report_no_issues')</p>
    @else
        <table>
            <thead>
            <tr>
                <th>@lang('crawler.report_col_severity')</th>
                <th>@lang('crawler.report_col_issue')</th>
                <th style="text-align:right">@lang('crawler.report_col_pages')</th>
                <th>@lang('crawler.report_col_recommendation')</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($score['topActions'] as $action)
                <tr>
                    <td>@lang('crawler.severity_'.$action['severity'])</td>
                    <td>@lang('crawler.issue.'.$action['code'])</td>
                    <td style="text-align:right">{{ $action['count'] }}</td>
                    <td>
                        @if (\Illuminate\Support\Facades\Lang::has('crawler.issue_help.'.$action['code']))
                            @lang('crawler.issue_help.'.$action['code'])
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
