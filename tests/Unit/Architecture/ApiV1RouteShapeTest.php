<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards /api/v1 URI shape against imperative action verbs.
 *
 * After inspecting the 2026-07-14 /api/v1 surface, a fail-closed “final segment
 * must be plural noun or {param}” rule would reject many legitimate singular
 * resource nouns (snapshot, summary, quota, stream, …) and known debt
 * (suggest, download). Primary guard is an expanded imperative-verb denylist;
 * an allowlisted non-plural check still catches new invent-a-verb segments.
 */
final class ApiV1RouteShapeTest extends TestCase
{
    private const IMPERATIVE_SEGMENTS = [
        'activate',
        'connect',
        'crawl',
        'delete',
        'disconnect',
        'execute',
        'expand',
        'export',
        'import',
        'refresh',
        'reset',
        'retry',
        'run',
        'search',
        'set-default',
        'start',
        'stop',
        'sync',
    ];

    /**
     * Known legitimate final segments that are neither `{param}` nor plural nouns.
     * Expand only when adding a vetted non-verb resource segment; prefer plurals.
     */
    private const ALLOWED_NON_PLURAL_FINALS = [
        'annotation',
        'contact-graph',
        'current',
        'delta',
        'download',
        'expand-previews',
        'progress',
        'quota',
        'rate-limit',
        'snapshot',
        'stream',
        'suggest',
        'summary',
        'thumbnail',
        'token-health',
        'usage',
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

    #[Test]
    public function api_v1_final_segments_are_plural_param_or_allowlisted(): void
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

            if (str_starts_with($normalized, '{') && str_ends_with($normalized, '}')) {
                continue;
            }

            if (str_ends_with($normalized, 's')) {
                continue;
            }

            if (in_array($normalized, self::ALLOWED_NON_PLURAL_FINALS, true)) {
                continue;
            }

            $violations[] = implode('|', $route->methods()).' '.$uri;
        }

        $this->assertSame(
            [],
            $violations,
            "API v1 final segments must be plural, {param}, or allowlisted:\n".implode("\n", $violations),
        );
    }
}
