@extends('reports.layout')

@section('body')
    <h2>Overview</h2>
    <div class="kpis">
        <div class="kpi"><div class="value">{{ number_format($totalClicks) }}</div><div class="label">Total clicks</div></div>
        <div class="kpi"><div class="value">{{ number_format($uniqueClicks) }}</div><div class="label">Unique clicks</div></div>
    </div>

    <h2>Clicks over time</h2>
    <canvas id="clicksChart"></canvas>

    <h2>Top links</h2>
    <table>
        <thead><tr><th>Link</th><th style="text-align:right">Clicks</th></tr></thead>
        <tbody>
        @forelse ($topLinks as $link)
            <tr><td>{{ $link['domain'] }}/{{ $link['slug'] }}</td><td style="text-align:right">{{ $link['clicks'] }}</td></tr>
        @empty
            <tr><td colspan="2" style="color:#94a3b8">No data</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="grid2">
        @include('reports.partials.breakdown', ['heading' => 'Country', 'rows' => $breakdowns['country']])
        @include('reports.partials.breakdown', ['heading' => 'Browser', 'rows' => $breakdowns['browser']])
        @include('reports.partials.breakdown', ['heading' => 'OS', 'rows' => $breakdowns['os']])
        @include('reports.partials.breakdown', ['heading' => 'Referrer', 'rows' => $breakdowns['domain']])
    </div>
@endsection
