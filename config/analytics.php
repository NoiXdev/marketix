<?php

return [
    /*
     | Default months of raw analytics rows (page_views/visits) to retain when a
     | Site sets no per-site retention_days override. Rows older than this are
     | hard-deleted by `php artisan analytics:prune` (scheduled daily).
     */
    'retention_months' => (int) env('ANALYTICS_RETENTION_MONTHS', 24),
];
