<?php

declare(strict_types=1);

return [
    'download' => [
        'timeout_seconds' => (int) env('XFLICKR_DOWNLOAD_TIMEOUT', 120),
    ],
];
