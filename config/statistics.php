<?php

return [
    /*
     | How many months of raw statistics rows to retain. Rows older than this
     | are hard-deleted by `php artisan statistics:prune` (scheduled daily).
     | Denormalized urls.clicks / urls.unique_clicks counters are unaffected.
     */
    'retention_months' => (int) env('STATISTICS_RETENTION_MONTHS', 12),
];
