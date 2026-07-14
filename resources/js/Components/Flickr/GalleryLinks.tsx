import ValueWithExternalLink from '@/Components/ui/ValueWithExternalLink';
import { catalogGalleryUrl, flickrGalleryPageUrl } from '@/lib/catalog';


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
