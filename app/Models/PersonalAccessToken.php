<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * ULID-aware override of Sanctum's PersonalAccessToken model.
 *
 * The stock model has an auto-incrementing integer key, but this app's
 * personal_access_tokens migration uses `ulid('id')->primary()` (to match
 * the ULID tokenable_id from ulidMorphs) — HasUlids is what actually
 * generates that id on creation. Registered via
 * Sanctum::usePersonalAccessTokenModel() in AppServiceProvider.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUlids;
}
