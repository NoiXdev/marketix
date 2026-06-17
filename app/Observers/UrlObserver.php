<?php

namespace App\Observers;

use App\Models\Url;

class UrlObserver
{
    public function creating(Url $url): void
    {
        $url->user_id ??= auth()->id();
    }
}
