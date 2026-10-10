<?php

namespace App\Support\Geo;

use App\Models\GeoName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Page views and clicks store GeoIP's English place name; the translations live here
class PlaceNames
{
    public const REGION = 'region';

    public const CITY = 'city';

    /** @param array<string, string> $names */
    public function remember(string $type, ?string $countryCode, ?string $name, array $names): void
    {
        if ($countryCode === null || $countryCode === '' || $name === null || $name === '' || $names === []) {
            return;
        }

        // Refreshed at most daily per place, not on every page view
        Cache::remember("geo-name:{$type}:{$countryCode}:".md5($name), now()->addDay(), function () use ($type, $countryCode, $name, $names) {
            $now = now();
            DB::table('geo_names')->upsert(
                [['type' => $type, 'country_code' => $countryCode, 'name' => $name, 'names' => json_encode($names), 'created_at' => $now, 'updated_at' => $now]],
                ['type', 'country_code', 'name'],
                ['names', 'updated_at'],
            );

            return true;
        });
    }

    /**
     * @param  iterable<mixed>  $rows
     * @return Collection<int, mixed>
     */
    public function localize(iterable $rows, string $type, string $column, string $countryColumn = 'country_code'): Collection
    {
        $rows = collect($rows)->values();
        $known = $this->lookup($type, $rows->pluck($column)->filter()->unique()->values()->all());

        return $rows->map(function ($row) use ($known, $column, $countryColumn) {
            $name = data_get($row, $column);
            $place = $known->first(fn (GeoName $g) => $g->name === $name && $g->country_code === data_get($row, $countryColumn))
                ?? $known->firstWhere('name', $name);

            data_set($row, "{$column}_name", $place === null ? $name : $this->translate($place));

            return $row;
        });
    }

    public function nameFor(string $type, string $name): string
    {
        $place = $this->lookup($type, [$name])->first();

        return $place === null ? $name : $this->translate($place);
    }

    private function translate(GeoName $place): string
    {
        return $place->names[App::getLocale()] ?? $place->names['en'] ?? $place->name;
    }

    /**
     * @param  list<string>  $names
     * @return Collection<int, GeoName>
     */
    private function lookup(string $type, array $names): Collection
    {
        return $names === [] ? collect() : GeoName::query()->where('type', $type)->whereIn('name', $names)->get();
    }
}
