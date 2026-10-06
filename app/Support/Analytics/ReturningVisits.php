<?php

namespace App\Support\Analytics;

use Illuminate\Support\Facades\DB;

final class ReturningVisits
{
    /**
     * Recompute visits.is_returning from history: a visit is returning when the
     * same visitor hash started an earlier visit on the same site.
     */
    public static function backfill(?string $siteId = null): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            // MySQL/MariaDB reject a subquery on the table being updated, so join a derived table instead
            $scope = $siteId === null ? '' : 'WHERE site_id = ?';

            DB::update(
                "UPDATE visits v
                JOIN (SELECT site_id, visitor_hash, MIN(started_at) AS first_started_at FROM visits {$scope} GROUP BY site_id, visitor_hash) f
                    ON f.site_id = v.site_id AND f.visitor_hash = v.visitor_hash
                SET v.is_returning = v.started_at > f.first_started_at",
                $siteId === null ? [] : [$siteId],
            );

            return;
        }

        DB::update(
            'UPDATE visits SET is_returning = started_at > (
                SELECT MIN(earlier.started_at) FROM visits earlier
                WHERE earlier.site_id = visits.site_id AND earlier.visitor_hash = visits.visitor_hash
            )'.($siteId === null ? '' : ' WHERE site_id = ?'),
            $siteId === null ? [] : [$siteId],
        );
    }
}
