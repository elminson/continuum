<?php

return [

    /*
    |--------------------------------------------------------------------------
    | NASA FIRMS access
    |--------------------------------------------------------------------------
    |
    | The public "active fire" text feeds (24h / 48h / 7d) need no key. The
    | Area API (used for multi-year backfills) requires a free MAP_KEY from
    | https://firms.modaps.eosdis.nasa.gov/api/map_key/ and is limited to
    | 5000 transactions per 10 minutes.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Daily schedule (UTC, HH:MM)
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Alert analyst (advisory AI reading of flagged periods)
    |--------------------------------------------------------------------------
    */

    'analyst' => [
        'provider' => env('ALERT_ANALYST_PROVIDER', 'ollama'),
        'model' => env('ALERT_ANALYST_MODEL', 'qwen2.5:7b-instruct'),
        // Readings per scheduled run; each takes about five seconds.
        'batch' => (int) env('ALERT_ANALYST_BATCH', 60),
    ],

    // Shared account for judges: sign-in details are locked and areas capped.
    'demo' => [
        'email' => env('CONTINUUM_DEMO_EMAIL'),
        'max_areas' => (int) env('CONTINUUM_DEMO_MAX_AREAS', 3),
        // Largest box, in square degrees (Hispaniola is about 55).
        'max_area_degrees' => (float) env('CONTINUUM_DEMO_MAX_AREA_DEGREES', 100),
    ],

    'schedule' => [
        // FIRMS refreshes its near-real-time global feeds through the day. Pull
        // the last 24 h every hour: detections are fingerprinted, so the
        // overlap between pulls is never counted twice.
        'ingest_public_cron' => env('CONTINUUM_INGEST_PUBLIC_CRON', '5 * * * *'),
        'ingest_public_window' => env('CONTINUUM_INGEST_PUBLIC_WINDOW', '24h'),
        // Fold whatever arrived into the calendars shortly after each pull.
        'harmonize_cron' => env('CONTINUUM_HARMONIZE_CRON', '25 * * * *'),
        // Only re-aggregate the last few days: the hourly pulls never reach further back.
        'harmonize_lookback_days' => (int) env('CONTINUUM_HARMONIZE_LOOKBACK_DAYS', 3),
        // Ask the alert analyst to read new or changed unusual periods after each harmonization.
        'triage_cron' => env('CONTINUUM_TRIAGE_CRON', '50 * * * *'),
    ],

    'firms' => [
        'base_url' => env('FIRMS_BASE_URL', 'https://firms.modaps.eosdis.nasa.gov'),
        'map_key' => env('FIRMS_MAP_KEY'),
        'timeout' => (int) env('FIRMS_TIMEOUT', 180),
        // Days of near-real-time data fetched on top of the standard archive when a region is backfilled.
        'nrt_tail_days' => (int) env('FIRMS_NRT_TAIL_DAYS', 70),
        // FIRMS charges per day requested, not per area, so each window is fetched for a
        // box padded by this many degrees: nearby areas added later reuse it for free.
        'fetch_padding_deg' => (float) env('FIRMS_FETCH_PADDING_DEG', 1.0),
        'rate_limit' => [
            // FIRMS charges more than one transaction for multi-day, large-area
            // requests (about 2 per requested day for a country-sized box), so
            // the budget is spent in weighted units: days * transaction_weight.
            'transactions' => (int) env('FIRMS_RATE_LIMIT', 4500),
            'transaction_weight' => (int) env('FIRMS_TRANSACTION_WEIGHT', 2),
            'decay_seconds' => 600,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Harmonization grid
    |--------------------------------------------------------------------------
    |
    | Detections from both instruments are snapped to a common, roughly
    | equal-area grid at MODIS-nadir scale (1 km). Counting "active cells"
    | instead of raw pixels removes the 3-7x VIIRS sub-pixel multiplicity
    | before any statistics are computed. The coarser lat/lon cell is used
    | for calendar heatmaps and map density.
    |
    */

    'grid_km' => (float) env('CONTINUUM_GRID_KM', 1),
    'calendar_cell_deg' => (float) env('CONTINUUM_CALENDAR_CELL_DEG', 0.25),

    'record' => [
        'modis_start' => '2000-11-01',
        'viirs_start' => '2012-01-20',
    ],

    'calibration' => [
        'method_version' => '2026.1',
        'min_days' => (int) env('CONTINUUM_CALIBRATION_MIN_DAYS', 5),
        'bootstrap_samples' => (int) env('CONTINUUM_BOOTSTRAP', 400),
        'holdout_fraction' => 0.25,
        'seed' => 20261115,
    ],

    'anomalies' => [
        'min_years' => 3,
        'thresholds' => [
            'watch' => 1.5,
            'warning' => 2.5,
            'critical' => 4.0,
        ],
    ],

    'samples_path' => env('CONTINUUM_SAMPLES_PATH', database_path('samples')),

    /*
    |--------------------------------------------------------------------------
    | Preset areas of interest
    |--------------------------------------------------------------------------
    */

    'presets' => [
        [
            'slug' => 'gran-chaco',
            'name' => 'Gran Chaco (Argentina · Paraguay · Bolivia)',
            'description' => 'Dry-forest and agricultural frontier; peak burning Aug-Oct. Photo credit for this challenge: CONAE.',
            'bbox' => [-70.0, -35.0, -55.0, -15.0],
            'color' => '#f97316',
        ],
        [
            'slug' => 'cerrado-amazon-arc',
            'name' => 'Cerrado & Amazon Arc of Deforestation (Brazil)',
            'description' => 'Savanna and forest-edge fires; Aug-Oct season.',
            'bbox' => [-65.0, -18.0, -42.0, -2.0],
            'color' => '#ef4444',
        ],
        [
            'slug' => 'northern-australia',
            'name' => 'Northern Australia savannas',
            'description' => 'Tropical savanna burning; dry season May-Nov.',
            'bbox' => [120.0, -22.0, 150.0, -10.0],
            'color' => '#eab308',
        ],
        [
            'slug' => 'borneo-sumatra',
            'name' => 'Borneo & Sumatra (Indonesia · Malaysia)',
            'description' => 'Peatland and plantation fires; El Niño sensitive; Aug-Oct.',
            'bbox' => [95.0, -8.0, 120.0, 6.0],
            'color' => '#a855f7',
        ],
    ],
];
