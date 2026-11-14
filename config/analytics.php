<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Visitor activity (decision D-016)
    |--------------------------------------------------------------------------
    |
    | Raw search and view events are kept for retention_days and then purged after
    | being rolled up; the daily summaries are kept. A visitor opening the same page
    | again within dedup_minutes is not counted twice. Search text is cut at term_max
    | characters.
    |
    */

    'retention_days' => (int) env('ANALYTICS_RETENTION_DAYS', 90),

    'dedup_minutes' => (int) env('ANALYTICS_DEDUP_MINUTES', 30),

    'term_max' => 100,

    'max_report_days' => 366,

    'default_report_days' => 30,

];
