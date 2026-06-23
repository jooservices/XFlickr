import type { CrawlType } from '@/types';

export interface CrawlTypeOption {
    value: CrawlType;
    label: string;
    description: string;
}

export const CONTACT_CATALOG_TYPES = [
    'photos',
    'favorites',
    'photosets',
    'galleries',
] as const satisfies readonly Exclude<CrawlType, 'contacts'>[];

export type ContactCatalogType = (typeof CONTACT_CATALOG_TYPES)[number];

export const CONTACT_CATALOG_LABELS: Record<ContactCatalogType, string> = {
    photos: 'Photos',
    favorites: 'Favorites',
    photosets: 'Photosets',
    galleries: 'Galleries',
};

export const CONTACT_CATALOG_DESCRIPTIONS: Record<ContactCatalogType, string> = {
    photos: 'Fetch photo catalog',
    favorites: 'Fetch favorites list',
    photosets: 'Fetch photosets',
    galleries: 'Fetch galleries',
};

export const CONTACT_CATALOG_COUNT_KEYS = {
    photos: 'photos_count',
    favorites: 'favorites_count',
    photosets: 'photosets_count',
    galleries: 'galleries_count',
} as const satisfies Record<ContactCatalogType, string>;

export const CONTACT_CATALOG_COLUMNS = CONTACT_CATALOG_TYPES.map((key) => ({
    key,
    label: CONTACT_CATALOG_LABELS[key],
    countKey: CONTACT_CATALOG_COUNT_KEYS[key],
}));

export const CONTACTS_DISCOVERY_CRAWL_OPTION = {
    value: 'contacts',
    label: 'Contacts',
    description: 'Discover contact list',
} as const satisfies CrawlTypeOption;

export const CRAWL_TYPE_OPTIONS: CrawlTypeOption[] = CONTACT_CATALOG_TYPES.map((value) => ({
    value,
    label: CONTACT_CATALOG_LABELS[value],
    description: CONTACT_CATALOG_DESCRIPTIONS[value],
}));

export const ALL_CRAWL_OPTION: CrawlTypeOption = {
    value: 'photos',
    label: 'All',
    description: 'Run all catalog crawls',
};

export const DOWNLOAD_OPTION = {
    label: 'Download',
    description: 'Save photos locally',
} as const;

export const UPLOAD_OPTION = {
    label: 'Upload',
    description: 'Push to cloud storage',
} as const;
