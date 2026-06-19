<?php

namespace App\Support;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;

class ActivityRecorder
{
    /**
     * Record a project-scoped event (membership, invitation, …).
     */
    public static function project(
        string $logName,
        string $description,
        ?string $projectId,
        ?Model $causer = null,
        ?Model $subject = null,
        array $properties = [],
    ): Activity {
        $logger = activity($logName)->withProperties($properties);

        if ($subject) {
            $logger->performedOn($subject);
        }

        if ($causer) {
            $logger->causedBy($causer);
        }

        /** @var Activity $activity */
        $activity = $logger->log($description);
        $activity->project_id = $projectId;
        $activity->save();

        return $activity;
    }

    /**
     * Record a global security event (project_id is always null).
     */
    public static function security(string $description, Model $causer, array $properties = []): Activity
    {
        $properties = array_merge([
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ], $properties);

        return self::project('security', $description, null, $causer, null, $properties);
    }
}
