<?php

namespace App\Support\Analytics;

use Illuminate\Database\Eloquent\Builder;

final class PathMatcher
{
    public static function apply(Builder $builder, string $pattern, string $column = 'path'): Builder
    {
        if (! str_ends_with($pattern, '/*')) {
            return $builder->where($column, $pattern);
        }

        $prefix = substr($pattern, 0, -1);
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $prefix);

        return $builder->whereRaw("{$column} LIKE ? ESCAPE '!'", [$escaped.'%']);
    }

    public static function normalize(string $pattern): string
    {
        $pattern = trim($pattern);

        return $pattern === '' || str_starts_with($pattern, '/') ? $pattern : '/'.$pattern;
    }
}
