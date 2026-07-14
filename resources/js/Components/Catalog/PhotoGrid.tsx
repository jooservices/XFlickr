import DownloadedBadge from '@/Components/Catalog/DownloadedBadge';
import PhotoDownloadingOverlay from '@/Components/Catalog/PhotoDownloadingOverlay';
import PhotoFailedBadge from '@/Components/Catalog/PhotoFailedBadge';
import PhotoGridTile from '@/Components/Catalog/PhotoGridTile';
import PhotoQueuedBadge from '@/Components/Catalog/PhotoQueuedBadge';
import PhotoTransferActions from '@/Components/Catalog/PhotoTransferActions';
import LoadingIndicator from '@/Components/ui/LoadingIndicator';
import { useInfiniteScroll } from '@/hooks/useInfiniteScroll';
import { flickrPhotoGridUrl } from '@/lib/flickrPhoto';
import type { Photo } from '@/types';

interface PhotoGridMacroProps {
    photos: Photo[];
    accountPublicId?: string | null;
    hasMore?: boolean;
    loadingMore?: boolean;
    onLoadMore?: () => void;
    onPhotoClick?: (photo: Photo) => void;
    onDownloadQueued?: (flickrPhotoId: string) => void;
}

export default function PhotoGridMacro({
    photos,
    accountPublicId,
    hasMore = false,
    loadingMore = false,
    onLoadMore,
    onPhotoClick,
    onDownloadQueued,
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
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4">
                {photos.map((photo) => {
                    const url = flickrPhotoGridUrl(photo);
                    const downloadStatus = photo.download_status ?? 'none';

                    return (
                        <PhotoGridTile
                            key={photo.id}
                            imageUrl={url}
                            alt={photo.title || 'Photo'}
                            onClick={onPhotoClick ? () => onPhotoClick(photo) : undefined}
                            revealTopRowOnHover={Boolean(accountPublicId)}
                            topRow={
                                accountPublicId ? (
                                    <PhotoTransferActions
                                        accountPublicId={accountPublicId}
                                        flickrPhotoId={photo.flickr_photo_id}
                                        onDownloadQueued={onDownloadQueued}
                                    />
                                ) : undefined
                            }
                            topRight={
                                downloadStatus === 'completed' && photo.stored_file_view_url ? (
                                    <DownloadedBadge href={photo.stored_file_view_url} />
                                ) : undefined
                            }
                            topLeft={
                                downloadStatus === 'failed' ? (
                                    <PhotoFailedBadge />
                                ) : downloadStatus === 'pending' ? (
                                    <PhotoQueuedBadge />
                                ) : undefined
                            }
                            center={downloadStatus === 'downloading' ? <PhotoDownloadingOverlay /> : undefined}
                            bottomRow={
                                <span className="truncate text-xs text-white drop-shadow">{photo.title || 'Untitled'}</span>
                            }
                        />
                    );
                })}
            </div>

            {hasMore ? <div ref={sentinelRef} className="h-8" aria-hidden /> : null}

            {loadingMore ? (
                <div className="flex justify-center">
                    <LoadingIndicator size="sm" label="Loading more…" />
                </div>
            ) : null}
        </div>
    );
}
