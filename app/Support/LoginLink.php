<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Short-lived, single-use login links used by support staff to enter a
 * customer account without their password or second factor.
 *
 * Only a SHA-256 hash of the token is stored, so nothing replayable is left
 * behind in the cache; consuming a link removes it.
 */
class LoginLink
{
    /** Minutes a freshly issued link stays valid. */
    public const TTL_MINUTES = 5;

    public static function issue(User $user): string
    {
        $token = Str::random(64);

        Cache::put(self::key($token), $user->getKey(), now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    public static function consume(string $token): ?User
    {
        $userId = Cache::pull(self::key($token));

        if ($userId === null) {
            return null;
        }

        return User::find($userId);
    }

    private static function key(string $token): string
    {
        return 'login-link:'.hash('sha256', $token);
    }
}
