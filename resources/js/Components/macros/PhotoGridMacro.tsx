import DownloadedBadge from '@/Components/DownloadedBadge';
import PhotoDownloadingOverlay from '@/Components/PhotoDownloadingOverlay';
import PhotoFailedBadge from '@/Components/PhotoFailedBadge';
import PhotoGridTile from '@/Components/PhotoGridTile';
import PhotoTransferActions from '@/Components/PhotoTransferActions';
import { useInfiniteScroll } from '@/hooks/useInfiniteScroll';
import { flickrPhotoThumbnailUrl } from '@/lib/flickrPhoto';
import type { Photo } from '@/types';

interface PhotoGridMacroProps {
    photos: Photo[];
    accountPublicId?: string | null;
    hasMore?: boolean;
    loadingMore?: boolean;
    onLoadMore?: () => void;
}

export default function PhotoGridMacro({
    photos,
    accountPublicId,
    hasMore = false,
    loadingMore = false,
    onLoadMore,
}: PhotoGridMacroProps) {
    const sentinelRef = useInfiniteScroll({
        hasMore,
        loading: loadingMore,
        onLoadMore: onLoadMore ?? (() => undefined),
    });

    if (photos.length === 0) {
        return <p className="text-sm text-slate-500">No photos match the current filters.</p>;
    }

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                {photos.map((photo) => {
                    const url = flickrPhotoThumbnailUrl(photo);
                    const downloadStatus = photo.download_status ?? 'none';

                    return (
                        <PhotoGridTile
                            key={photo.id}
                            imageUrl={url}
                            alt={photo.title || 'Photo'}
                            revealTopRowOnHover={Boolean(accountPublicId)}
                            topRow={
                                accountPublicId ? (
                                    <PhotoTransferActions
                                        accountPublicId={accountPublicId}
                                        flickrPhotoId={photo.flickr_photo_id}
                                    />
                                ) : undefined
                            }
                            topRight={
                                downloadStatus === 'completed' && photo.stored_file_view_url ? (
                                    <DownloadedBadge href={photo.stored_file_view_url} />
                                ) : undefined
                            }
                            topLeft={downloadStatus === 'failed' ? <PhotoFailedBadge /> : undefined}
                            center={downloadStatus === 'downloading' ? <PhotoDownloadingOverlay /> : undefined}
                            bottomRow={
                                <span className="truncate text-xs text-white drop-shadow">{photo.title || 'Untitled'}</span>
                            }
                        />
                    );
                })}
            </div>

            {hasMore ? <div ref={sentinelRef} className="h-8" aria-hidden /> : null}

            {loadingMore ? <p className="text-center text-sm text-slate-500">Loading more…</p> : null}
        </div>
    );
}
