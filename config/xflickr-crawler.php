<?php

declare(strict_types=1);

return [
    'default_app_profile' => env('XFLICKR_DEFAULT_APP_PROFILE', 'main'),

    'queue' => env('XFLICKR_QUEUE', 'xflickr'),

    'tables' => [
        'connections' => 'xflickr_connections',
        'contacts' => 'xflickr_contacts',
        'photos' => 'xflickr_photos',
        'photosets' => 'xflickr_photosets',
        'galleries' => 'xflickr_galleries',
        'photoset_photo' => 'xflickr_photoset_photo',
        'gallery_photo' => 'xflickr_gallery_photo',
        'crawl_runs' => 'xflickr_crawl_runs',
        'crawl_targets' => 'xflickr_crawl_targets',
        'api_logs' => 'xflickr_api_logs',
        'favorites' => 'xflickr_favorites',
        'connection_contacts' => 'xflickr_connection_contacts',
    ],

    'throttle' => [
        'max_requests_per_hour' => (int) env('XFLICKR_MAX_REQUESTS_PER_HOUR', 3300),
        'min_gap_ms' => (int) env('XFLICKR_MIN_GAP_MS', 333),
        'window_seconds' => (int) env('XFLICKR_WINDOW_SECONDS', 3600),
        'rate_limit_backoff_seconds' => (int) env('XFLICKR_RATE_LIMIT_BACKOFF_SECONDS', 3600),
    ],

    'crawl' => [
        'per_page' => (int) env('XFLICKR_CRAWL_PER_PAGE', 500),
        'dispatch_limit' => (int) env('XFLICKR_DISPATCH_LIMIT', 0),
        'people_photos_safe_search' => (int) env('XFLICKR_PEOPLE_PHOTOS_SAFE_SEARCH', 1),
        'stall_minutes' => (int) env('XFLICKR_STALL_MINUTES', 15),
    ],

    'bulk' => [
        'chunk_size' => (int) env('XFLICKR_BULK_CHUNK_SIZE', 250),
    ],
];
