<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Modules\Crawler\Support\XFlickrConfig;
use Modules\Spider\Support\SpiderRuntimeConfig;
use Modules\Storage\Support\TransferRuntimeConfig;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $spider = app(SpiderRuntimeConfig::class);
        $transfer = app(TransferRuntimeConfig::class);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() === null ? null : [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ],
            ],
            'app' => [
                'name' => config('app.name'),
                'global_pause' => XFlickrConfig::globalPause(),
                'spider' => [
                    'enabled' => $spider->enabled(),
                    'max_depth' => $spider->maxDepth(),
                    'max_new_contacts_per_run' => $spider->maxNewContactsPerRun(),
                    'max_contacts_total' => $spider->maxContactsTotal(),
                ],
                'delete_local_after_upload' => $transfer->shouldDeleteLocalAfterUpload(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
