import Button from '@/Components/Button';
import ContactNsidLinks from '@/Components/ContactNsidLinks';
import CrawlActionBar from '@/Components/CrawlActionBar';
import FlickrPhotoIdLinks from '@/Components/FlickrPhotoIdLinks';
import Modal from '@/Components/Modal';
import PhotoDownloadedCell from '@/Components/PhotoDownloadedCell';
import PhotoMembershipLinks from '@/Components/PhotoMembershipLinks';
import { buttonVariants } from '@/lib/buttonVariants';
import { flickrPhotoPageUrl } from '@/lib/catalog';
import { crawlSubjectForPhoto } from '@/lib/crawlSubject';
import { flickrPhotoPreviewUrl } from '@/lib/flickrPhoto';
import type { Photo } from '@/types';

interface PhotoDetailModalProps {
    photo: Photo | null;
    accountPublicId?: string | null;
    onClose: () => void;
}

export default function PhotoDetailModal({ photo, accountPublicId, onClose }: PhotoDetailModalProps) {
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
            <Modal.Footer className="justify-between sm:justify-between">
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
                        />
                    ) : null}
                </div>
                <Button type="button" variant="secondary" size="sm" onClick={onClose}>
                    Close
                </Button>
            </Modal.Footer>
        </Modal>
    );
}
