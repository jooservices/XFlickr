import { flickrGalleryPageUrl, photoSubtext } from '@/lib/catalog';

import ValueWithExternalLink from './ValueWithExternalLink';

export interface FlickrGalleryIdLinksProps {
    galleryId: string;
    ownerNsid: string;
    title?: string | null;
    subtext?: string | null;
    showSubtext?: boolean;
}

export default function FlickrGalleryIdLinks({
    galleryId,
    ownerNsid,
    title,
    subtext,
    showSubtext = true,
}: FlickrGalleryIdLinksProps) {
    const resolvedSubtext =
        subtext !== undefined ? subtext : showSubtext ? photoSubtext(title) : null;

    return (
        <ValueWithExternalLink
            value={galleryId}
            externalHref={flickrGalleryPageUrl(ownerNsid, galleryId)}
            externalTitle="Open gallery on Flickr"
            subtext={resolvedSubtext}
            mono
        />
    );
}
