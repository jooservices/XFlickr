import { connectionsPath } from '@/lib/connections';

export type CommandPaletteNavItem = {
    id: string;
    label: string;
    href: string;
    section: 'Navigate' | 'Storage' | 'Settings';
    keywords: string[];
};

/** Static app destinations for the ⌘K / Ctrl+K command palette. */
export function commandPaletteNavigationItems(): CommandPaletteNavItem[] {
    return [
        {
            id: 'nav-dashboard',
            label: 'Dashboard',
            href: '/dashboard',
            section: 'Navigate',
            keywords: ['home', 'overview'],
        },
        {
            id: 'nav-contacts',
            label: 'Contacts',
            href: '/contacts',
            section: 'Navigate',
            keywords: ['people', 'nsid'],
        },
        {
            id: 'nav-photos',
            label: 'Photos',
            href: '/photos',
            section: 'Navigate',
            keywords: ['catalog', 'images'],
        },
        {
            id: 'nav-photosets',
            label: 'Photosets',
            href: '/photosets',
            section: 'Navigate',
            keywords: ['albums', 'sets'],
        },
        {
            id: 'nav-favorites',
            label: 'Favorites',
            href: '/favorites',
            section: 'Navigate',
            keywords: ['faves', 'liked'],
        },
        {
            id: 'nav-galleries',
            label: 'Galleries',
            href: '/galleries',
            section: 'Navigate',
            keywords: ['collections'],
        },
        {
            id: 'nav-operations',
            label: 'Operations',
            href: '/operations',
            section: 'Navigate',
            keywords: ['crawl', 'jobs', 'transfers', 'horizon', 'batches'],
        },
        {
            id: 'nav-sync',
            label: 'Sync',
            href: '/sync',
            section: 'Navigate',
            keywords: ['transfers', 'integrity', 'download', 'upload', 'batches'],
        },
        {
            id: 'nav-activity',
            label: 'Activity',
            href: '/activity',
            section: 'Navigate',
            keywords: ['audit', 'logs', 'domain', 'events', 'correlation', 'trail'],
        },
        {
            id: 'nav-connections',
            label: 'Connections',
            href: connectionsPath(),
            section: 'Settings',
            keywords: ['flickr', 'oauth', 'accounts', 'apps', 'credentials'],
        },
        {
            id: 'nav-connections-flickr',
            label: 'Connections — Flickr',
            href: connectionsPath({ provider: 'flickr' }),
            section: 'Settings',
            keywords: ['flickr', 'token', 'reconnect'],
        },
        {
            id: 'nav-connections-storage',
            label: 'Connections — Storage',
            href: connectionsPath({ provider: 'storage' }),
            section: 'Settings',
            keywords: ['google', 'drive', 'photos', 'onedrive', 'r2'],
        },
        {
            id: 'nav-settings',
            label: 'Settings',
            href: '/settings',
            section: 'Settings',
            keywords: ['runtime', 'config', 'spider', 'general'],
        },
        {
            id: 'nav-storage-google-photos',
            label: 'Google Photos browse',
            href: '/storages/google-photos',
            section: 'Storage',
            keywords: ['gp', 'backup'],
        },
        {
            id: 'nav-storage-google-drive',
            label: 'Google Drive browse',
            href: '/storages/google-drive',
            section: 'Storage',
            keywords: ['gdrive', 'backup'],
        },
        {
            id: 'nav-storage-onedrive',
            label: 'OneDrive browse',
            href: '/storages/onedrive',
            section: 'Storage',
            keywords: ['microsoft', 'backup'],
        },
        {
            id: 'nav-storage-r2',
            label: 'Cloudflare R2 browse',
            href: '/storages/r2',
            section: 'Storage',
            keywords: ['s3', 'bucket', 'backup'],
        },
    ];
}
