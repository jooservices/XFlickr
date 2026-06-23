export function catalogOwnerUrl(path: string, ownerNsid: string): string {
    const param = path === '/favorites' ? 'subject_nsid' : 'owner_nsid';
    const params = new URLSearchParams({ [param]: ownerNsid });

    return `${path}?${params.toString()}`;
}

export function readOwnerNsidFromUrl(): string {
    if (typeof window === 'undefined') {
        return '';
    }

    const params = new URLSearchParams(window.location.search);

    return params.get('owner_nsid')?.trim() ?? params.get('subject_nsid')?.trim() ?? '';
}

export function flickrPeopleUrl(nsid: string): string {
    return `https://www.flickr.com/people/${encodeURIComponent(nsid)}/`;
}

export function flickrPhotoPageUrl(ownerNsid: string, photoId: string): string {
    return `https://www.flickr.com/photos/${encodeURIComponent(ownerNsid)}/${encodeURIComponent(photoId)}`;
}

export function flickrPhotosetPageUrl(ownerNsid: string, photosetId: string): string {
    return `https://www.flickr.com/photos/${encodeURIComponent(ownerNsid)}/sets/${encodeURIComponent(photosetId)}/`;
}

export function flickrGalleryPageUrl(ownerNsid: string, galleryId: string): string {
    return `https://www.flickr.com/photos/${encodeURIComponent(ownerNsid)}/galleries/${encodeURIComponent(galleryId)}/`;
}

export function catalogPhotosetUrl(ownerNsid: string, accountPublicId?: string | null): string {
    const params = new URLSearchParams({ owner_nsid: ownerNsid });

    if (accountPublicId) {
        return `/flickr/accounts/${encodeURIComponent(accountPublicId)}/photosets?${params.toString()}`;
    }

    return `/photosets?${params.toString()}`;
}

export function catalogGalleryUrl(ownerNsid: string, accountPublicId?: string | null): string {
    const params = new URLSearchParams({ owner_nsid: ownerNsid });

    if (accountPublicId) {
        return `/flickr/accounts/${encodeURIComponent(accountPublicId)}/galleries?${params.toString()}`;
    }

    return `/galleries?${params.toString()}`;
}

export function photoSubtext(title?: string | null): string {
    const trimmed = title?.trim();

    return trimmed ? trimmed : 'Untitled';
}
