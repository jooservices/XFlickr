<?php

declare(strict_types=1);

namespace App\Support;

final class CuratedConfigCatalog
{
    /**
     * @return list<array{
     *     path: string,
     *     label: string,
     *     type: string,
     *     default: mixed,
     *     group: string,
     *     is_core: bool,
     *     sort: int
     * }>
     */
    public function definitions(): array
    {
        return [
            [
                'path' => 'xflickr.global_pause',
                'label' => 'Global crawl pause',
                'type' => 'bool',
                'default' => false,
                'group' => 'Operations',
                'is_core' => true,
                'sort' => 10,
            ],
            [
                'path' => 'xflickr.dispatch_limit',
                'label' => 'Dispatch limit per tick',
                'type' => 'int',
                'default' => (int) config('xflickr-crawler.crawl.dispatch_limit', 0),
                'group' => 'Operations',
                'is_core' => true,
                'sort' => 20,
            ],
            [
                'path' => 'xflickr.max_requests_per_hour',
                'label' => 'Max requests per hour',
                'type' => 'int',
                'default' => (int) config('xflickr-crawler.throttle.max_requests_per_hour', 3300),
                'group' => 'Throttle',
                'is_core' => true,
                'sort' => 10,
            ],
            [
                'path' => 'xflickr_crawl.per_page',
                'label' => 'Crawl per page',
                'type' => 'int',
                'default' => (int) config('xflickr-crawler.crawl.per_page', 500),
                'group' => 'Crawl',
                'is_core' => true,
                'sort' => 10,
            ],
            [
                'path' => 'xflickr_crawl.stall_minutes',
                'label' => 'Stall minutes',
                'type' => 'int',
                'default' => (int) config('xflickr-crawler.crawl.stall_minutes', 15),
                'group' => 'Crawl',
                'is_core' => true,
                'sort' => 20,
            ],
            [
                'path' => 'xflickr_crawl.people_photos_safe_search',
                'label' => 'People photos safe search',
                'type' => 'int',
                'default' => (int) config('xflickr-crawler.crawl.people_photos_safe_search', 1),
                'group' => 'Crawl',
                'is_core' => true,
                'sort' => 30,
            ],
            [
                'path' => 'xflickr.default_app_profile',
                'label' => 'Default app profile',
                'type' => 'string',
                'default' => (string) config('xflickr-crawler.default_app_profile', 'main'),
                'group' => 'App',
                'is_core' => true,
                'sort' => 10,
            ],
            [
                'path' => 'spider.enabled',
                'label' => 'Spider mode enabled',
                'type' => 'bool',
                'default' => false,
                'group' => 'Spider',
                'is_core' => true,
                'sort' => 10,
            ],
            [
                'path' => 'spider.max_depth',
                'label' => 'Spider max depth',
                'type' => 'int',
                'default' => 2,
                'group' => 'Spider',
                'is_core' => true,
                'sort' => 20,
            ],
            [
                'path' => 'spider.max_new_contacts_per_run',
                'label' => 'Spider new contacts per expansion tick',
                'type' => 'int',
                'default' => 25,
                'group' => 'Spider',
                'is_core' => true,
                'sort' => 30,
            ],
            [
                'path' => 'spider.max_contacts_total',
                'label' => 'Spider max contacts per run',
                'type' => 'int',
                'default' => 500,
                'group' => 'Spider',
                'is_core' => true,
                'sort' => 40,
            ],
            [
                'path' => 'horizon.general_max_processes',
                'label' => 'Horizon general workers (per container)',
                'type' => 'int',
                'default' => (int) (config('horizon.environments.production.supervisor-1.maxProcesses') ?? 8),
                'group' => 'Queue',
                'is_core' => true,
                'sort' => 10,
            ],
            [
                'path' => 'horizon.downloads_max_processes',
                'label' => 'Horizon download workers (per container)',
                'type' => 'int',
                'default' => (int) (config('horizon.environments.production.supervisor-downloads.maxProcesses') ?? 4),
                'group' => 'Queue',
                'is_core' => true,
                'sort' => 20,
            ],
            [
                'path' => 'horizon.uploads_max_processes',
                'label' => 'Horizon upload workers (per container)',
                'type' => 'int',
                'default' => (int) (config('horizon.environments.production.supervisor-uploads.maxProcesses') ?? 2),
                'group' => 'Queue',
                'is_core' => true,
                'sort' => 30,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function corePaths(): array
    {
        return array_column($this->definitions(), 'path');
    }

    public function isCorePath(string $path): bool
    {
        return in_array($path, $this->corePaths(), true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDefinition(string $path): ?array
    {
        foreach ($this->definitions() as $definition) {
            if ($definition['path'] === $path) {
                return $definition;
            }
        }

        return null;
    }
}
