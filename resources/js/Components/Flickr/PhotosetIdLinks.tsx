import ValueWithExternalLink from '@/Components/ui/ValueWithExternalLink';
import { flickrPhotosetPageUrl, photoSubtext } from '@/lib/catalog';


export interface FlickrPhotosetIdLinksProps {
    photosetId: string;
    ownerNsid: string;
    title?: string | null;
    subtext?: string | null;
    showSubtext?: boolean;
}

export default function FlickrPhotosetIdLinks({
    photosetId,
    ownerNsid,
    title,
    subtext,
    showSubtext = true,
}: FlickrPhotosetIdLinksProps) {
    const resolvedSubtext =
        subtext !== undefined ? subtext : showSubtext ? photoSubtext(title) : null;

    return (
        <ValueWithExternalLink
            value={photosetId}
            externalHref={flickrPhotosetPageUrl(ownerNsid, photosetId)}
            externalTitle="Open photoset on Flickr"
            subtext={resolvedSubtext}
            mono
        />
    );
}
