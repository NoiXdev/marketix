<div>
    <h2>{{ $heading }}</h2>
    <table>
        <thead><tr><th>{{ $heading }}</th><th style="text-align:right">Clicks</th></tr></thead>
        <tbody>
        @forelse ($rows as $row)
            <tr><td>{{ $row['label'] }}</td><td style="text-align:right">{{ $row['count'] }}</td></tr>
        @empty
            <tr><td colspan="2" style="color:#94a3b8">No data</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
