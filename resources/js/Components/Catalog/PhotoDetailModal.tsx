import { router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useCallback, useEffect, useMemo } from 'react';

import PhotoDownloadedCell from '@/Components/Catalog/PhotoDownloadedCell';
import PhotoMembershipLinks from '@/Components/Catalog/PhotoMembershipLinks';
import ContactNsidLinks from '@/Components/Contacts/NsidLinks';
import CrawlActionBar from '@/Components/Flickr/CrawlActionBar';
import FlickrPhotoIdLinks from '@/Components/Flickr/PhotoIdLinks';
import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';
import { buttonVariants } from '@/lib/buttonVariants';
import { flickrPhotoPageUrl } from '@/lib/catalog';
import { crawlSubjectForPhoto } from '@/lib/crawlSubject';
import { flickrAccountPath } from '@/lib/flickrAccount';
import { flickrPhotoPreviewUrl } from '@/lib/flickrPhoto';
import { adjacentPhotoIndex, shouldIgnorePhotoModalShortcut } from '@/lib/photoNavigation';
import type { PageProps, Photo } from '@/types';

interface PhotoDetailModalProps {
    photo: Photo | null;
    photos?: Photo[];
    onSelectPhoto?: (photo: Photo) => void;
    accountPublicId?: string | null;
    onClose: () => void;
    onDownloadQueued?: (flickrPhotoId: string) => void;
}

export default function PhotoDetailModal({
    photo,
    photos = [],
    onSelectPhoto,
    accountPublicId,
    onClose,
    onDownloadQueued,
}: PhotoDetailModalProps) {
    const { app } = usePage<PageProps>().props;
    const crawlPaused = app.global_pause ?? false;

    const navigationEnabled = photos.length > 0 && onSelectPhoto !== undefined;

    const canGoPrev = useMemo(() => {
        if (!navigationEnabled || photo === null) {
            return false;
        }

        return adjacentPhotoIndex(photos, photo, -1) !== null;
    }, [navigationEnabled, photo, photos]);

    const canGoNext = useMemo(() => {
        if (!navigationEnabled || photo === null) {
            return false;
        }

        return adjacentPhotoIndex(photos, photo, 1) !== null;
    }, [navigationEnabled, photo, photos]);

    const navigate = useCallback(
        (direction: -1 | 1) => {
            if (!navigationEnabled || photo === null || onSelectPhoto === undefined) {
                return;
            }

            const nextIndex = adjacentPhotoIndex(photos, photo, direction);
            if (nextIndex === null) {
                return;
            }

            onSelectPhoto(photos[nextIndex]);
        },
        [navigationEnabled, onSelectPhoto, photo, photos],
    );

    const queueDownload = useCallback(() => {
        if (!accountPublicId || photo === null || crawlPaused) {
            return;
        }

        const flickrPhotoId = photo.flickr_photo_id;

        router.post(
            flickrAccountPath(accountPublicId, '/download'),
            { flickr_photo_id: flickrPhotoId },
            {
                preserveScroll: true,
                onSuccess: () => onDownloadQueued?.(flickrPhotoId),
            },
        );
    }, [accountPublicId, crawlPaused, onDownloadQueued, photo]);

    useEffect(() => {
        if (photo === null) {
            return;
        }

        function onKeyDown(event: KeyboardEvent) {
            if (shouldIgnorePhotoModalShortcut(event)) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                onClose();
                return;
            }

            if (navigationEnabled) {
                if (event.key === 'ArrowLeft' || event.key === 'k') {
                    event.preventDefault();
                    navigate(-1);
                    return;
                }

                if (event.key === 'ArrowRight' || event.key === 'j') {
                    event.preventDefault();
                    navigate(1);
                    return;
                }
            }

            if ((event.key === 'd' || event.key === 'D') && accountPublicId) {
                event.preventDefault();
                queueDownload();
            }
        }

        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [accountPublicId, navigate, navigationEnabled, onClose, photo, queueDownload]);

    if (photo === null) {
        return null;
    }

    const title = photo.title?.trim() || 'Untitled';
    const previewUrl = flickrPhotoPreviewUrl(photo);
    const flickrUrl = flickrPhotoPageUrl(photo.owner_nsid, photo.flickr_photo_id);

    return (
        <Modal open onClose={onClose} titleId="photo-detail-title" size="xl">
            <Modal.Header title={title} />
            <Modal.Body className="space-y-4">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start">
                    <div className="flex min-h-48 flex-1 items-center justify-center overflow-hidden rounded-lg bg-slate-100 dark:bg-slate-800">
                        {previewUrl ? (
                            <img
                                src={previewUrl}
                                alt={title}
                                className="max-h-[min(60vh,28rem)] w-full object-contain"
                                loading="eager"
                            />
                        ) : (
                            <p className="text-sm text-slate-500">No preview available.</p>
                        )}
                    </div>

                    <dl className="grid w-full shrink-0 gap-3 text-sm lg:w-72">
                        <div className="space-y-1 border-b border-slate-100 pb-2">
                            <dt className="text-slate-500">Photo ID</dt>
                            <dd>
                                <FlickrPhotoIdLinks
                                    photoId={photo.flickr_photo_id}
                                    ownerNsid={photo.owner_nsid}
                                    title={photo.title}
                                    showSubtext={false}
                                />
                            </dd>
                        </div>
                        <div className="space-y-1 border-b border-slate-100 pb-2">
                            <dt className="text-slate-500">Owner</dt>
                            <dd>
                                <ContactNsidLinks nsid={photo.owner_nsid} accountPublicId={accountPublicId} />
                            </dd>
                        </div>
                        <div className="space-y-1 border-b border-slate-100 pb-2">
                            <dt className="text-slate-500">Downloaded</dt>
                            <dd>
                                <PhotoDownloadedCell
                                    status={photo.download_status}
                                    viewUrl={photo.stored_file_view_url}
                                />
                            </dd>
                        </div>
                        <div className="space-y-1 border-b border-slate-100 pb-2">
                            <dt className="text-slate-500">Photosets</dt>
                            <dd>
                                <PhotoMembershipLinks
                                    items={photo.photosets ?? []}
                                    kind="photoset"
                                    accountPublicId={accountPublicId}
                                />
                            </dd>
                        </div>
                        <div className="space-y-1">
                            <dt className="text-slate-500">Galleries</dt>
                            <dd>
                                <PhotoMembershipLinks
                                    items={photo.galleries ?? []}
                                    kind="gallery"
                                    accountPublicId={accountPublicId}
                                />
                            </dd>
                        </div>
                    </dl>
                </div>
            </Modal.Body>
            <Modal.Footer className="flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-wrap items-center gap-2">
                    <a
                        href={flickrUrl}
                        target="_blank"
                        rel="noreferrer"
                        className={buttonVariants({ variant: 'secondary', size: 'sm' })}
                    >
                        Open on Flickr
                    </a>
                    {accountPublicId ? (
                        <CrawlActionBar
                            scope="photo"
                            accountPublicId={accountPublicId}
                            flickrPhotoId={photo.flickr_photo_id}
                            subjectLabel={crawlSubjectForPhoto(photo)}
                            showCrawl={false}
                            label="Actions"
                            onDownloadQueued={() => onDownloadQueued?.(photo.flickr_photo_id)}
                        />
                    ) : null}
                </div>

                {navigationEnabled ? (
                    <div className="flex flex-wrap items-center justify-center gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onClick={() => navigate(-1)}
                            disabled={!canGoPrev}
                            aria-label="Previous photo"
                        >
                            <ChevronLeft className="h-4 w-4" aria-hidden />
                            Previous
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onClick={() => navigate(1)}
                            disabled={!canGoNext}
                            aria-label="Next photo"
                        >
                            Next
                            <ChevronRight className="h-4 w-4" aria-hidden />
                        </Button>
                        <p className="hidden text-xs text-slate-400 sm:block">
                            ← → or J/K navigate
                            {accountPublicId ? ' · D download' : ''} · Esc close
                        </p>
                    </div>
                ) : null}

                <Button type="button" variant="secondary" size="sm" onClick={onClose}>
                    Close
                </Button>
            </Modal.Footer>
        </Modal>
    );
}
