import { catalogGalleryUrl, flickrGalleryPageUrl } from '@/lib/catalog';

import ValueWithExternalLink from './ValueWithExternalLink';

export interface FlickrGalleryLinksProps {
    galleryId: string;
    ownerNsid: string;
    title?: string | null;
    accountPublicId?: string | null;
}

export default function FlickrGalleryLinks({
    galleryId,
    ownerNsid,
    title,
    accountPublicId,
}: FlickrGalleryLinksProps) {
    return (
        <ValueWithExternalLink
            value={title?.trim() || 'Untitled'}
            href={catalogGalleryUrl(ownerNsid, accountPublicId)}
            externalHref={flickrGalleryPageUrl(ownerNsid, galleryId)}
            externalTitle="Open gallery on Flickr"
        />
    );
}
