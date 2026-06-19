@php
    /** @var array{value:int,previous:int,percent:?int,isNew:bool} $clicksChange */
    /** @var array{value:int,previous:int,percent:?int,isNew:bool} $uniqueChange */
    $fmtChange = function (array $c): string {
        if ($c['isNew']) {
            return 'new';
        }
        if ($c['percent'] === null) {
            return '—';
        }
        $arrow = $c['percent'] > 0 ? '▲' : ($c['percent'] < 0 ? '▼' : '');
        return trim($arrow.' '.$c['percent'].'%');
    };
@endphp

<x-mail::message>
# {{ $frequencyLabel }} report — {{ $projectName }}

Statistics for **{{ $periodLabel }}**.

<x-mail::table>
| Metric | This period | vs. previous |
|:-------|:------------|:-------------|
| Clicks | {{ $totalClicks }} | {{ $fmtChange($clicksChange) }} |
| Unique clicks | {{ $uniqueClicks }} | {{ $fmtChange($uniqueChange) }} |
</x-mail::table>

@if (count($topLinks) > 0)
## Top links

<x-mail::table>
| Link | Clicks |
|:-----|-------:|
@foreach ($topLinks as $link)
| {{ $link['domain'] }}/{{ $link['slug'] }} | {{ $link['clicks'] }} |
@endforeach
</x-mail::table>
@endif

@if (count($topCountries) > 0)
## Top countries

<x-mail::table>
| Country | Clicks |
|:--------|-------:|
@foreach ($topCountries as $row)
| {{ $row['label'] }} | {{ $row['count'] }} |
@endforeach
</x-mail::table>
@endif

@if (count($topReferrers) > 0)
## Top referrers

<x-mail::table>
| Referrer | Clicks |
|:---------|-------:|
@foreach ($topReferrers as $row)
| {{ $row['label'] }} | {{ $row['count'] }} |
@endforeach
</x-mail::table>
@endif

<x-mail::button :url="$settingsUrl">
Manage report settings
</x-mail::button>

You're receiving this because {{ $frequencyLabel }} reports are enabled for this project. Change or turn them off any time from the link above.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
