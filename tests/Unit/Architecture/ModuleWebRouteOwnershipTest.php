<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ModuleWebRouteOwnershipTest extends TestCase
{
    #[Test]
    public function business_module_web_routes_are_registered(): void
    {
        $uris = collect(Route::getRoutes())
            ->map(fn ($route) => '/'.$route->uri())
            ->all();

        $expected = [
            '/login',
            '/logout',
            '/register',
            '/forgot-password',
            '/reset-password/{token}',
            '/dashboard',
            '/settings',
            '/connections',
            '/contacts',
            '/flickr/accounts',
            '/flickr/oauth',
            '/operations',
            '/photos',
            '/storages/google-photos',
        ];

        foreach ($expected as $uri) {
            $this->assertContains($uri, $uris, "Missing registered web route: {$uri}");
        }
    }
}
