<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\UrlStatus;
use App\Models\Url;

trait InteractsWithUrlSettings
{
    /**
     * The user-editable link settings a backing Url shares with a standalone
     * link. A blank password is dropped so "leave blank to keep" works.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function linkSettingAttributes(array $data): array
    {
        return $this->dropBlankPassword([
            'status'             => $data['status'] ?? UrlStatus::ACTIVATED->value,
            'password'           => $data['password'] ?? null,
            'expired_at'         => $data['expired_at'] ?? null,
            'targeting_geo'      => $data['targeting_geo'] ?? null,
            'targeting_device'   => $data['targeting_device'] ?? null,
            'targeting_language' => $data['targeting_language'] ?? null,
            'targeting_ab'       => $data['targeting_ab'] ?? null,
        ]);
    }

    /**
     * Write-only password: a blank value means "keep the current one".
     *
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    protected function dropBlankPassword(array $attrs): array
    {
        if (blank($attrs['password'] ?? null)) {
            unset($attrs['password']);
        }

        return $attrs;
    }

    /**
     * @param  array<int, string>  $pixelIds
     */
    protected function syncUrlPixels(Url $url, array $pixelIds): void
    {
        $url->pixels()->sync($pixelIds);
    }
}
