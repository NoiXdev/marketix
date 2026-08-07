@extends('reports.email.layout')

@section('body')
    <h1 style="font-size:18px;margin:0 0 4px;color:#0f172a;">{{ $title }}</h1>
    <p style="font-size:13px;color:#64748b;margin:0 0 20px;">{{ $rangeLabel }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;">
        <tr>
            <td width="50%" style="padding:12px;border:1px solid #e2e8f0;border-radius:6px;">
                <div style="font-size:22px;font-weight:bold;color:#0f172a;">{{ number_format($totalClicks) }}</div>
                <div style="font-size:11px;color:#64748b;text-transform:uppercase;">{{ __('reports.report.total_clicks') }}</div>
            </td>
            <td width="12">&nbsp;</td>
            <td width="50%" style="padding:12px;border:1px solid #e2e8f0;border-radius:6px;">
                <div style="font-size:22px;font-weight:bold;color:#0f172a;">{{ number_format($uniqueClicks) }}</div>
                <div style="font-size:11px;color:#64748b;text-transform:uppercase;">{{ __('reports.report.unique_clicks') }}</div>
            </td>
        </tr>
    </table>

    <h2 style="font-size:14px;margin:20px 0 8px;color:#0f172a;">{{ __('reports.report.trend') }}</h2>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px;margin-bottom:12px;">
        <tr>
            <th align="left" style="border-bottom:1px solid #e2e8f0;padding:4px 8px;color:#64748b;">Date</th>
            <th align="right" style="border-bottom:1px solid #e2e8f0;padding:4px 8px;color:#64748b;">{{ __('reports.report.total_clicks') }}</th>
            <th align="right" style="border-bottom:1px solid #e2e8f0;padding:4px 8px;color:#64748b;">{{ __('reports.report.unique_clicks') }}</th>
        </tr>
        @forelse ($timeSeries as $point)
            <tr>
                <td style="padding:4px 8px;border-bottom:1px solid #f1f5f9;">{{ $point['date'] }}</td>
                <td align="right" style="padding:4px 8px;border-bottom:1px solid #f1f5f9;">{{ $point['clicks'] }}</td>
                <td align="right" style="padding:4px 8px;border-bottom:1px solid #f1f5f9;">{{ $point['unique'] }}</td>
            </tr>
        @empty
            <tr><td colspan="3" style="padding:4px 8px;color:#94a3b8;">No data</td></tr>
        @endforelse
    </table>

    <h2 style="font-size:14px;margin:20px 0 8px;color:#0f172a;">{{ __('reports.report.top_links') }}</h2>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px;margin-bottom:12px;">
        <tr>
            <th align="left" style="border-bottom:1px solid #e2e8f0;padding:4px 8px;color:#64748b;">Link</th>
            <th align="right" style="border-bottom:1px solid #e2e8f0;padding:4px 8px;color:#64748b;">{{ __('reports.report.total_clicks') }}</th>
        </tr>
        @forelse ($topLinks as $link)
            <tr>
                <td style="padding:4px 8px;border-bottom:1px solid #f1f5f9;">{{ $link['domain'] }}/{{ $link['slug'] }}</td>
                <td align="right" style="padding:4px 8px;border-bottom:1px solid #f1f5f9;">{{ $link['clicks'] }}</td>
            </tr>
        @empty
            <tr><td colspan="2" style="padding:4px 8px;color:#94a3b8;">No data</td></tr>
        @endforelse
    </table>

    @foreach (['country' => 'top_countries', 'browser' => 'top_browsers', 'os' => 'top_os', 'domain' => 'top_referrers'] as $column => $labelKey)
        <h2 style="font-size:14px;margin:20px 0 8px;color:#0f172a;">{{ __('reports.report.'.$labelKey) }}</h2>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px;margin-bottom:12px;">
            <tr>
                <th align="left" style="border-bottom:1px solid #e2e8f0;padding:4px 8px;color:#64748b;">{{ __('reports.report.'.$labelKey) }}</th>
                <th align="right" style="border-bottom:1px solid #e2e8f0;padding:4px 8px;color:#64748b;">{{ __('reports.report.total_clicks') }}</th>
            </tr>
            @forelse ($breakdowns[$column] ?? [] as $row)
                <tr>
                    <td style="padding:4px 8px;border-bottom:1px solid #f1f5f9;">{{ $row['label'] }}</td>
                    <td align="right" style="padding:4px 8px;border-bottom:1px solid #f1f5f9;">{{ $row['count'] }}</td>
                </tr>
            @empty
                <tr><td colspan="2" style="padding:4px 8px;color:#94a3b8;">No data</td></tr>
            @endforelse
        </table>
    @endforeach
@endsection
