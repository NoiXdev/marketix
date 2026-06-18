@extends('reports.layout')

@section('body')
    <h2>Link report</h2>
    <h2>Overview</h2>
    <div class="kpis">
        <div class="kpi"><div class="value">{{ number_format($totalClicks) }}</div><div class="label">Total clicks</div></div>
        <div class="kpi"><div class="value">{{ number_format($uniqueClicks) }}</div><div class="label">Unique clicks</div></div>
    </div>

    <h2>Clicks over time</h2>
    <canvas id="clicksChart"></canvas>

    <div class="grid2">
        @include('reports.partials.breakdown', ['heading' => 'Country', 'rows' => $breakdowns['country']])
        @include('reports.partials.breakdown', ['heading' => 'City', 'rows' => $breakdowns['city']])
        @include('reports.partials.breakdown', ['heading' => 'Browser', 'rows' => $breakdowns['browser']])
        @include('reports.partials.breakdown', ['heading' => 'OS', 'rows' => $breakdowns['os']])
    </div>

    <h2>Recent clicks</h2>
    <table>
        <thead><tr><th>When</th><th>Country</th><th>City</th><th>Browser</th><th>OS</th></tr></thead>
        <tbody>
        @forelse ($recentClicks as $click)
            <tr>
                <td>{{ $click['created_at'] }}</td>
                <td>{{ $click['country'] ?? '—' }}</td>
                <td>{{ $click['city'] ?? '—' }}</td>
                <td>{{ $click['browser'] ?? '—' }}</td>
                <td>{{ $click['os'] ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" style="color:#94a3b8">No data</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
