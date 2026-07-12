import type { BreadcrumbItem } from '@/Components/Breadcrumbs';
import { flickrAccountPath } from '@/lib/flickrAccount';
import type { FlickrAccount } from '@/types';

export function accountLabel(account: FlickrAccount): string {
    return account.username ?? account.nsid;
}

export function flickrRootCrumb(): BreadcrumbItem {
    return { label: 'Flickr', href: '/flickr/accounts' };
}

export function flickrAccountPageCrumbs(
    account: FlickrAccount,
    options?: { linkAccount?: boolean },
): BreadcrumbItem[] {
    const linkAccount = options?.linkAccount ?? true;

    return [
        flickrRootCrumb(),
        {
            label: accountLabel(account),
            ...(linkAccount ? { href: flickrAccountPath(account.public_id) } : {}),
        },
    ];
}

export function flickrContactShowCrumbs(account: FlickrAccount, contactLabel: string): BreadcrumbItem[] {
    return [
        flickrRootCrumb(),
        { label: 'Contacts', href: flickrAccountPath(account.public_id, '/contacts') },
        { label: contactLabel },
    ];
}

export function catalogPageCrumbs(pageLabel: string, account?: FlickrAccount | null): BreadcrumbItem[] {
    if (account) {
        return flickrAccountPageCrumbs(account);
    }

    return [{ label: 'Dashboard', href: '/dashboard' }, { label: pageLabel }];
}

export function catalogPhotosetShowCrumbs(
    photosetTitle: string,
    options?: {
        account?: FlickrAccount | null;
        photosetsHref?: string;
    },
): BreadcrumbItem[] {
    const photosetsHref = options?.photosetsHref ?? (options?.account ? flickrAccountPath(options.account.public_id, '/photosets') : '/photosets');

    if (options?.account) {
        return [...flickrAccountPageCrumbs(options.account), { label: 'Photosets', href: photosetsHref }, { label: photosetTitle }];
    }

    return [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Photosets', href: photosetsHref },
        { label: photosetTitle },
    ];
}

const settingsTabLabels: Record<'general' | 'flickr' | 'storage', string> = {
    general: 'General',
    flickr: 'Flickr',
    storage: 'Storages',
};

export function settingsCrumbs(tab: 'general' | 'flickr' | 'storage'): BreadcrumbItem[] {
    if (tab === 'general') {
        return [{ label: 'Settings' }];
    }

    return [{ label: 'Settings', href: '/settings' }, { label: settingsTabLabels[tab] }];
}

export function storageBrowseCrumbs(providerLabel: string): BreadcrumbItem[] {
    return [{ label: 'Storage' }, { label: providerLabel }];
}
