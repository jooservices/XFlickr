import FlickrGalleryLinks from '@/Components/FlickrGalleryLinks';
import FlickrPhotosetLinks from '@/Components/FlickrPhotosetLinks';
import type { PhotoMembership } from '@/types';

interface PhotoMembershipLinksProps {
    items: PhotoMembership[];
    kind: 'photoset' | 'gallery';
    accountPublicId?: string | null;
}

export default function PhotoMembershipLinks({
    items,
    kind,
    accountPublicId,
}: PhotoMembershipLinksProps) {
    if (items.length === 0) {
        return <span className="text-slate-400">—</span>;
    }

    return (
        <div className="space-y-2">
            {items.map((item) =>
                kind === 'photoset' ? (
                    <FlickrPhotosetLinks
                        key={`photoset-${item.flickr_id}`}
                        photosetId={item.flickr_id}
                        ownerNsid={item.owner_nsid}
                        title={item.title}
                        accountPublicId={accountPublicId}
                    />
                ) : (
                    <FlickrGalleryLinks
                        key={`gallery-${item.flickr_id}`}
                        galleryId={item.flickr_id}
                        ownerNsid={item.owner_nsid}
                        title={item.title}
                        accountPublicId={accountPublicId}
                    />
                ),
            )}
        </div>
    );
}
