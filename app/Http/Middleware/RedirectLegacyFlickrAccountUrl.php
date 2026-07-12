<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JOOservices\XFlickrCrawler\Models\Connection;
use Modules\Flickr\Support\ConnectionPublicIdService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirect legacy NSID-based Flickr account URLs to UUID public_id paths.
 */
final class RedirectLegacyFlickrAccountUrl
{
    public function __construct(
        private readonly ConnectionPublicIdService $publicIds,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();

        if (! preg_match('#^(?:api/)?flickr/accounts/([^/]+)(/.*)?$#', $path, $matches)) {
            return $next($request);
        }

        $segment = $matches[1];

        if (Str::isUuid($segment)) {
            return $next($request);
        }

        $connection = Connection::query()
            ->where('connection_key', urldecode($segment))
            ->first();

        if ($connection === null) {
            return $next($request);
        }

        $publicId = $this->publicIds->ensure($connection);
        $prefix = str_starts_with($path, 'api/') ? '/api/flickr/accounts/' : '/flickr/accounts/';
        $suffix = $matches[2] ?? '';
        $target = $prefix.$publicId.$suffix;
        $query = $request->getQueryString();

        if (is_string($query) && $query !== '') {
            $target .= '?'.$query;
        }

        return redirect()->to($target);
    }
}
