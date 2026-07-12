<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ApiV1RouteShapeTest extends TestCase
{
    private const IMPERATIVE_SEGMENTS = [
        'delete',
        'start',
        'stop',
        'reset',
        'retry',
        'sync',
        'set-default',
        'crawl',
        'expand',
    ];

    #[Test]
    public function api_v1_routes_do_not_use_imperative_uri_verbs(): void
    {
        $violations = [];

        foreach (Route::getRoutes() as $route) {
            $uri = '/'.$route->uri();

            if (! str_starts_with($uri, '/api/v1/')) {
                continue;
            }

            $segments = array_values(array_filter(explode('/', $uri)));
            $final = end($segments);

            if (! is_string($final) || $final === '') {
                continue;
            }

            $normalized = strtolower($final);

            if (in_array($normalized, self::IMPERATIVE_SEGMENTS, true)) {
                $violations[] = implode('|', $route->methods()).' '.$uri;
            }
        }

        $this->assertSame([], $violations, "Imperative API v1 URI verbs found:\n".implode("\n", $violations));
    }
}
