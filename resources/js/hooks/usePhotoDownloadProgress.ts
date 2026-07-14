import { useCallback, useEffect, useMemo } from 'react';

import { usePolledResource } from '@/hooks/usePolledResource';
import { API_V1 } from '@/lib/apiPaths';
import type { Photo, PhotoDownloadStatus } from '@/types';

interface PhotoDownloadProgressRow {
    flickr_photo_id: string;
    download_status: PhotoDownloadStatus;
    stored_file_uuid: string | null;
    stored_file_view_url: string | null;
}

interface PhotoDownloadProgressResponse {
    data: {
        photos: PhotoDownloadProgressRow[];
    };
}

const LIVE_DOWNLOAD_STATUSES: PhotoDownloadStatus[] = ['pending', 'downloading'];

export function isLiveDownloadStatus(status: PhotoDownloadStatus | undefined): boolean {
    return status !== undefined && LIVE_DOWNLOAD_STATUSES.includes(status);
}

function applyDownloadMeta(photo: Photo, update: PhotoDownloadProgressRow): Photo {
    // Keep optimistic Queued until the worker has created a StoredFile row.
    if (update.download_status === 'none' && isLiveDownloadStatus(photo.download_status)) {
        return photo;
    }

    return {
        ...photo,
        download_status: update.download_status,
        stored_file_uuid: update.stored_file_uuid,
        stored_file_view_url: update.stored_file_view_url,
    };
}

export function usePhotoDownloadProgress(
    photos: Photo[],
    patchData: (updater: (current: Photo[]) => Photo[]) => void,
): {
    markPhotosPending: (flickrPhotoIds: string[] | 'visible') => void;
} {
    const markPhotosPending = useCallback(
        (flickrPhotoIds: string[] | 'visible') => {
            const idSet = flickrPhotoIds === 'visible' ? null : new Set(flickrPhotoIds);

            patchData((current) =>
                current.map((photo) => {
                    if (idSet !== null && !idSet.has(photo.flickr_photo_id)) {
                        return photo;
                    }

                    if (photo.download_status === 'completed' || photo.download_status === 'downloading') {
                        return photo;
                    }

                    return {
                        ...photo,
                        download_status: 'pending',
                        stored_file_uuid: photo.stored_file_uuid ?? null,
                        stored_file_view_url: null,
                    };
                }),
            );
        },
        [patchData],
    );

    const liveProgressIds = useMemo(
        () =>
            photos
                .filter((photo) => isLiveDownloadStatus(photo.download_status))
                .map((photo) => photo.flickr_photo_id)
                .slice(0, 200)
                .join(','),
        [photos],
    );

    const shouldPollProgress = liveProgressIds !== '';
    const { data: progressData } = usePolledResource<PhotoDownloadProgressResponse>(
        shouldPollProgress ? `${API_V1}/flickr/catalog/photos/progress` : null,
        {
            intervalMs: 3000,
            enabled: shouldPollProgress,
            params: { ids: liveProgressIds },
        },
    );

    useEffect(() => {
        const updates = progressData?.data.photos;

        if (!updates || updates.length === 0) {
            return;
        }

        const byId = new Map(updates.map((row) => [row.flickr_photo_id, row]));

        patchData((current) =>
            current.map((photo) => {
                const update = byId.get(photo.flickr_photo_id);

                return update ? applyDownloadMeta(photo, update) : photo;
            }),
        );
    }, [patchData, progressData]);

    return { markPhotosPending };
}
