@extends('reports.layout')

@php
    // SiteAnalyticsReport::viewData() has no 'timeSeries' key (it uses
    // 'series' instead) — default it so the inherited layout's chart
    // script (which always reads $timeSeries) doesn't hit an undefined
    // variable. No <canvas id="clicksChart"> is rendered below, so the
    // script's early-return keeps this a no-op either way.
    $timeSeries = $timeSeries ?? [];
@endphp

@section('body')
    <h2>Overview</h2>
    <div class="kpis">
        <div class="kpi"><div class="value">{{ number_format($visits) }}</div><div class="label">Visits</div></div>
        <div class="kpi"><div class="value">{{ number_format($page_views) }}</div><div class="label">Page views</div></div>
    </div>

    <h2>Page views over time</h2>
    <table>
        <thead><tr><th>Date</th><th style="text-align:right">Views</th><th style="text-align:right">Visitors</th></tr></thead>
        <tbody>
        @forelse ($series as $point)
            <tr>
                <td>{{ $point['date'] }}</td>
                <td style="text-align:right">{{ $point['views'] }}</td>
                <td style="text-align:right">{{ $point['visitors'] }}</td>
            </tr>
        @empty
            <tr><td colspan="3" style="color:#94a3b8">No data</td></tr>
        @endforelse
        </tbody>
    </table>

    <h2>Goals</h2>
    <table>
        <thead><tr><th>Goal</th><th style="text-align:right">Conversions</th><th style="text-align:right">Visitors</th><th style="text-align:right">Rate</th></tr></thead>
        <tbody>
        @forelse ($goals as $goal)
            <tr>
                <td>{{ $goal['name'] }}</td>
                <td style="text-align:right">{{ $goal['conversions'] }}</td>
                <td style="text-align:right">{{ $goal['visitors'] }}</td>
                <td style="text-align:right">{{ $goal['rate'] }}%</td>
            </tr>
        @empty
            <tr><td colspan="4" style="color:#94a3b8">No data</td></tr>
        @endforelse
        </tbody>
    </table>

    <h2>Top events</h2>
    <table>
        <thead><tr><th>Event</th><th style="text-align:right">Count</th><th style="text-align:right">Visitors</th></tr></thead>
        <tbody>
        @forelse ($top_events as $event)
            <tr>
                <td>{{ $event['name'] }}</td>
                <td style="text-align:right">{{ $event['count'] }}</td>
                <td style="text-align:right">{{ $event['visitors'] }}</td>
            </tr>
        @empty
            <tr><td colspan="3" style="color:#94a3b8">No data</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
