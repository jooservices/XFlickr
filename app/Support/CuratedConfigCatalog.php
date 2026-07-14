<?php

declare(strict_types=1);

namespace App\Support;

final class CuratedConfigCatalog
{
    /**
     * @return list<array{
     *     path: string,
     *     label: string,
     *     description: string,
     *     group: string,
     *     group_label: string,
     *     section: string,
     *     tier: string,
     *     type: string,
     *     default: mixed,
     *     is_core: bool,
     *     sort: int
     * }>
     */
    public function definitions(): array
    {
        return [
            $this->define(
                path: 'xflickr.global_pause',
                label: 'Global crawl pause',
                description: 'When enabled, crawl jobs will not dispatch until pause is cleared. Toggle from the header Pause/Resume control or here; a banner appears across the app.',
                type: 'bool',
                default: false,
                groupLabel: 'Operations',
                section: 'operations',
                tier: 'operational',
                sort: 10,
            ),
            $this->define(
                path: 'xflickr.dispatch_limit',
                label: 'Dispatch limit per tick',
                description: 'Maximum crawl targets queued per dispatcher tick. Use 0 for the package default.',
                type: 'int',
                default: (int) config('xflickr-crawler.crawl.dispatch_limit', 0),
                groupLabel: 'Operations',
                section: 'operations',
                tier: 'operational',
                sort: 20,
            ),
            $this->define(
                path: 'xflickr.max_requests_per_hour',
                label: 'Max requests per hour',
                description: 'Per-account Flickr API hourly budget before the account enters cooldown.',
                type: 'int',
                default: (int) config('xflickr-crawler.throttle.max_requests_per_hour', 3300),
                groupLabel: 'Throttle',
                section: 'crawl',
                tier: 'operational',
                sort: 10,
            ),
            $this->define(
                path: 'xflickr_crawl.per_page',
                label: 'Crawl per page',
                description: 'Page size for Flickr list API calls during crawls (photos, contacts, etc.).',
                type: 'int',
                default: (int) config('xflickr-crawler.crawl.per_page', 500),
                groupLabel: 'Crawl',
                section: 'crawl',
                tier: 'operational',
                sort: 10,
            ),
            $this->define(
                path: 'xflickr_crawl.stall_minutes',
                label: 'Stall minutes',
                description: 'Minutes without progress before a crawl run is treated as stalled.',
                type: 'int',
                default: (int) config('xflickr-crawler.crawl.stall_minutes', 15),
                groupLabel: 'Crawl',
                section: 'crawl',
                tier: 'expert',
                sort: 20,
            ),
            $this->define(
                path: 'xflickr_crawl.safe_search',
                label: 'Crawl safe search (0 = off)',
                description: 'Flickr safe_search filter for crawl list requests. 0 disables the filter.',
                type: 'int',
                default: (int) config('xflickr-crawler.crawl.safe_search', 0),
                groupLabel: 'Crawl',
                section: 'crawl',
                tier: 'operational',
                sort: 30,
            ),
            $this->define(
                path: 'xflickr_crawl.privacy_filter',
                label: 'Crawl privacy filter (0 = off)',
                description: 'Flickr privacy_filter for crawl list requests. 0 disables the filter.',
                type: 'int',
                default: (int) config('xflickr-crawler.crawl.privacy_filter', 0),
                groupLabel: 'Crawl',
                section: 'crawl',
                tier: 'expert',
                sort: 35,
            ),
            $this->define(
                path: 'xflickr_crawl.people_photos_safe_search',
                label: 'People photos safe search (legacy)',
                description: 'Legacy people-photos safe search. Prefer Crawl safe search for new installs.',
                type: 'int',
                default: (int) config('xflickr-crawler.crawl.people_photos_safe_search', 0),
                groupLabel: 'Crawl',
                section: 'crawl',
                tier: 'expert',
                sort: 40,
            ),
            $this->define(
                path: 'xflickr.default_app_profile',
                label: 'Default app profile',
                description: 'Flickr API app profile key used when connecting accounts (see Flickr → Apps).',
                type: 'string',
                default: (string) config('xflickr-crawler.default_app_profile', 'main'),
                groupLabel: 'App',
                section: 'application',
                tier: 'operational',
                sort: 10,
            ),
            $this->define(
                path: 'spider.enabled',
                label: 'Spider mode enabled',
                description: 'Opt-in breadth-first contact expansion. Toggle from the header Spider control or here. Leave off unless you intend automatic discovery crawls.',
                type: 'bool',
                default: false,
                groupLabel: 'Spider',
                section: 'discovery',
                tier: 'operational',
                sort: 10,
            ),
            $this->define(
                path: 'spider.max_depth',
                label: 'Spider max depth',
                description: 'Maximum contact-graph depth for spider runs (depth 0 is the seed account).',
                type: 'int',
                default: 2,
                groupLabel: 'Spider',
                section: 'discovery',
                tier: 'operational',
                sort: 20,
            ),
            $this->define(
                path: 'spider.max_new_contacts_per_run',
                label: 'Spider new contacts per expansion tick',
                description: 'How many newly discovered contacts may be enqueued on each spider expansion tick.',
                type: 'int',
                default: 25,
                groupLabel: 'Spider',
                section: 'discovery',
                tier: 'operational',
                sort: 30,
            ),
            $this->define(
                path: 'spider.max_contacts_total',
                label: 'Spider max contacts per run',
                description: 'Hard cap on total contacts processed in a single spider run.',
                type: 'int',
                default: 500,
                groupLabel: 'Spider',
                section: 'discovery',
                tier: 'operational',
                sort: 40,
            ),
            $this->define(
                path: 'full_pass.max_depth',
                label: 'Full contact pass max depth',
                description: 'Maximum depth for manual full contact-pass expansion from the contact graph.',
                type: 'int',
                default: 1,
                groupLabel: 'Spider',
                section: 'discovery',
                tier: 'operational',
                sort: 50,
            ),
            $this->define(
                path: 'contact_graph.initial_direct_limit',
                label: 'Contact graph initial direct contacts',
                description: 'How many direct contacts to load when first opening the contact graph.',
                type: 'int',
                default: 100,
                groupLabel: 'Contact graph',
                section: 'discovery',
                tier: 'expert',
                sort: 10,
            ),
            $this->define(
                path: 'contact_graph.load_more_step',
                label: 'Contact graph load-more step',
                description: 'Additional direct contacts fetched each time Load more is used on the graph.',
                type: 'int',
                default: 100,
                groupLabel: 'Contact graph',
                section: 'discovery',
                tier: 'expert',
                sort: 20,
            ),
            $this->define(
                path: 'horizon.general_max_processes',
                label: 'Horizon general workers (per container)',
                description: 'Max Horizon workers for the general supervisor. Saving or resetting restarts Horizon.',
                type: 'int',
                default: (int) (config('horizon.environments.production.supervisor-1.maxProcesses') ?? 8),
                groupLabel: 'Queue',
                section: 'operations',
                tier: 'expert',
                sort: 10,
            ),
            $this->define(
                path: 'horizon.downloads_max_processes',
                label: 'Horizon download workers (per container)',
                description: 'Max Horizon workers for download jobs. Saving or resetting restarts Horizon.',
                type: 'int',
                default: (int) (config('horizon.environments.production.supervisor-downloads.maxProcesses') ?? 4),
                groupLabel: 'Queue',
                section: 'operations',
                tier: 'expert',
                sort: 20,
            ),
            $this->define(
                path: 'horizon.uploads_max_processes',
                label: 'Horizon upload workers (per container)',
                description: 'Max Horizon workers for upload jobs. Saving or resetting restarts Horizon.',
                type: 'int',
                default: (int) (config('horizon.environments.production.supervisor-uploads.maxProcesses') ?? 2),
                groupLabel: 'Queue',
                section: 'operations',
                tier: 'expert',
                sort: 30,
            ),
            $this->define(
                path: 'xflickr_download.timeout_seconds',
                label: 'Photo download HTTP timeout (seconds)',
                description: 'Maximum seconds to wait for Flickr original download HTTP responses. Increase for large originals on slow links.',
                type: 'int',
                default: (int) config('xflickr.download.timeout_seconds', 120),
                groupLabel: 'Transfer / Downloads',
                section: 'transfers',
                tier: 'operational',
                sort: 10,
            ),
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

    /**
     * @return array{
     *     path: string,
     *     label: string,
     *     description: string,
     *     group: string,
     *     group_label: string,
     *     section: string,
     *     tier: string,
     *     type: string,
     *     default: mixed,
     *     is_core: bool,
     *     sort: int
     * }
     */
    private function define(
        string $path,
        string $label,
        string $description,
        string $type,
        mixed $default,
        string $groupLabel,
        string $section,
        string $tier,
        int $sort,
    ): array {
        return [
            'path' => $path,
            'label' => $label,
            'description' => $description,
            'group' => $groupLabel,
            'group_label' => $groupLabel,
            'section' => $section,
            'tier' => $tier,
            'type' => $type,
            'default' => $default,
            'is_core' => true,
            'sort' => $sort,
        ];
    }
}
