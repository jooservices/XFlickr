<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ModuleApiRouteOwnershipTest extends TestCase
{
    #[Test]
    public function business_module_api_v1_routes_are_registered(): void
    {
        $uris = collect(Route::getRoutes())
            ->map(fn ($route) => '/'.$route->uri())
            ->all();

        $expected = [
            '/api/v1/dashboard/snapshot',
            '/api/v1/operations/snapshot',
            '/api/v1/operations/stream',
            '/api/v1/flickr/rate-limit',
            '/api/v1/flickr/accounts/{connection}',
            '/api/v1/flickr/accounts/{connection}/crawl-runs',
            '/api/v1/flickr/accounts/{connection}/contacts',
            '/api/v1/flickr/accounts/{connection}/contact-graph',
            '/api/v1/flickr/accounts/{connection}/expand-previews',
            '/api/v1/flickr/accounts/{connection}/spider-runs/current',
            '/api/v1/flickr/accounts/{connection}/transfers',
            '/api/v1/flickr/catalog/photos',
            '/api/v1/flickr/catalog/photos/progress',
            '/api/v1/stored-files/{uuid}',
            '/api/v1/storage/accounts',
            '/api/v1/storage/{provider}/files',
        ];

        foreach ($expected as $uri) {
            $this->assertContains($uri, $uris, "Missing registered route: {$uri}");
        }
    }
}
