<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait FiltersIndex
{
    private const PAGE_SIZES = [25, 50, 100];

    protected function search(Request $request): string
    {
        return trim($request->string('search')->toString());
    }

    protected function perPage(Request $request): int
    {
        $size = $request->integer('per_page', self::PAGE_SIZES[0]);

        return in_array($size, self::PAGE_SIZES, true) ? $size : self::PAGE_SIZES[0];
    }

    /**
     * @return array{search: string, per_page: int}
     */
    protected function filters(Request $request): array
    {
        return ['search' => $this->search($request), 'per_page' => $this->perPage($request)];
    }
}
