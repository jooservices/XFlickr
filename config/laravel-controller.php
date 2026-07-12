<?php

return [
    'response_formatter' => null,

    'keys' => [
        'success' => 'success',
        'code' => 'code',
        'message' => 'message',
        'data' => 'data',
        'errors' => 'errors',
        'meta' => 'meta',
        'trace_id' => 'trace_id',
        'warnings' => 'warnings',
    ],

    'use_translations' => false,

    'trace_id' => [
        'header' => 'X-Trace-ID',
    ],

    'envelope_204' => true,

    'success_codes' => null,

    'validation' => [
        'message' => 'Unprocessable Entity',
    ],

    'routes' => [
        'enabled' => false,
        'prefix' => 'api/v1',
        'auto_map_host_routes' => false,
    ],

    'status' => [
        'include_version' => true,
        'include_environment' => true,
        'include_maintenance' => true,
        'checks' => [],
        'checks_timeout_seconds' => 5,
    ],

    'pagination_links' => true,

    'item_links' => true,
    'item_links_default' => null,
];
