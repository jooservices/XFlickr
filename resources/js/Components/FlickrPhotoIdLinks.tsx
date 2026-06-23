import { flickrPhotoPageUrl, photoSubtext } from '@/lib/catalog';

import ValueWithExternalLink from './ValueWithExternalLink';

export interface FlickrPhotoIdLinksProps {
    photoId: string;
    ownerNsid: string;
    title?: string | null;
    subtext?: string | null;
    showSubtext?: boolean;
}

export default function FlickrPhotoIdLinks({
    photoId,
    ownerNsid,
    title,
    subtext,
    showSubtext = true,
}: FlickrPhotoIdLinksProps) {
    const resolvedSubtext =
        subtext !== undefined ? subtext : showSubtext ? photoSubtext(title) : null;

    return (
        <ValueWithExternalLink
            value={photoId}
            externalHref={flickrPhotoPageUrl(ownerNsid, photoId)}
            externalTitle="Open photo on Flickr"
            subtext={resolvedSubtext}
            mono
        />
    );
}
