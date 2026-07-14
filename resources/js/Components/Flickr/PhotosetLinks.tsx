import ValueWithExternalLink from '@/Components/ui/ValueWithExternalLink';
import { catalogPhotosetUrl, flickrPhotosetPageUrl } from '@/lib/catalog';


export interface FlickrPhotosetLinksProps {
    photosetId: string;
    ownerNsid: string;
    title?: string | null;
    accountPublicId?: string | null;
}

export default function FlickrPhotosetLinks({
    photosetId,
    ownerNsid,
    title,
    accountPublicId,
}: FlickrPhotosetLinksProps) {
    return (
        <ValueWithExternalLink
            value={title?.trim() || 'Untitled'}
            href={catalogPhotosetUrl(ownerNsid, accountPublicId)}
            externalHref={flickrPhotosetPageUrl(ownerNsid, photosetId)}
            externalTitle="Open photoset on Flickr"
        />
    );
}
