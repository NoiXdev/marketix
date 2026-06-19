<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 24mm 16mm; }
        * { font-family: DejaVu Sans, Arial, sans-serif; color: #0f172a; }
        body { font-size: 12px; margin: 0; }
        .cover { page-break-after: always; padding-top: 60mm; text-align: center; }
        .cover .brand { font-size: 28px; font-weight: 700; color: #4f46e5; }
        .cover h1 { font-size: 22px; margin: 16px 0 4px; }
        .cover .meta { color: #64748b; font-size: 13px; }
        h2 { font-size: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 4px; margin-top: 28px; }
        .kpis { display: flex; gap: 12px; margin: 16px 0; }
        .kpi { flex: 1; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; }
        .kpi .value { font-size: 24px; font-weight: 700; }
        .kpi .label { color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #f1f5f9; }
        th { color: #64748b; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
        .grid2 { display: flex; flex-wrap: wrap; gap: 16px; }
        .grid2 > div { flex: 1 1 45%; }
        canvas { width: 100% !important; height: 220px !important; }
    </style>
</head>
<body>
    <div class="cover">
        <div class="brand">
            @if(!empty($brandEmailLogoUrl))
                <img src="{{ $brandEmailLogoUrl }}" alt="{{ config('app.name') }}" style="max-height:48px">
            @else
                {{ config('app.name') }}
            @endif
        </div>
        <h1>{{ $title }}</h1>
        <div class="meta">{{ $subtitle }}</div>
        <div class="meta">{{ $rangeLabel }} · generated {{ $generatedAt }}</div>
    </div>

    @yield('body')

    <script src="{{ asset('vendor/chart/chart.umd.js') }}"></script>
    <script>
        (function () {
            var series = @json($timeSeries);
            var el = document.getElementById('clicksChart');
            if (!el || !window.Chart) return;
            new Chart(el.getContext('2d'), {
                type: 'line',
                data: {
                    labels: series.map(function (d) { return d.date; }),
                    datasets: [
                        { label: 'Clicks', data: series.map(function (d) { return d.clicks; }), borderColor: '#4f46e5', backgroundColor: 'rgba(79,70,229,.1)', fill: true, tension: .3 },
                        { label: 'Unique', data: series.map(function (d) { return d.unique; }), borderColor: '#10b981', fill: false, tension: .3 }
                    ]
                },
                options: { responsive: false, animation: false, plugins: { legend: { position: 'bottom' } } }
            });
        })();
    </script>
</body>
</html>
