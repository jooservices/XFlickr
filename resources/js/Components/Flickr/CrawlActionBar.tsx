import { router, usePage } from '@inertiajs/react';

import CrawlDropdown, { type CrawlSubjectLabel } from '@/Components/Flickr/CrawlDropdown';
import { flickrAccountPath, flickrContactPath } from '@/lib/flickrAccount';
import type { CrawlType, CrawlTypeState, PageProps } from '@/types';

export type { CrawlSubjectLabel };

export type CrawlActionScope = 'account' | 'contact' | 'photo';

export interface CrawlActionBarProps {
    scope: CrawlActionScope;
    accountPublicId: string;
    contactNsid?: string;
    flickrPhotoId?: string;
    subjectLabel?: CrawlSubjectLabel;
    typeStates?: Partial<Record<CrawlType, CrawlTypeState>>;
    size?: 'sm' | 'md';
    variant?: 'default' | 'primary';
    showCrawl?: boolean;
    showDownload?: boolean;
    showUpload?: boolean;
    label?: string;
    onDownloadQueued?: () => void;
}

export default function CrawlActionBar({
    scope,
    accountPublicId,
    contactNsid,
    flickrPhotoId,
    subjectLabel,
    typeStates,
    size = 'sm',
    variant = 'default',
    showCrawl = true,
    showDownload = true,
    showUpload = true,
    label,
    onDownloadQueued,
}: CrawlActionBarProps) {
    const { app } = usePage<PageProps>().props;
    const crawlPaused = app.global_pause ?? false;

    const startCrawl = (types: CrawlType[]) => {
        if (crawlPaused) {
            return;
        }

        if (scope === 'contact') {
            if (!contactNsid) {
                return;
            }

            router.post(
                flickrContactPath(accountPublicId, contactNsid, '/crawl'),
                { types },
                { preserveScroll: true },
            );

            return;
        }

        router.post(flickrAccountPath(accountPublicId, '/crawl'), { types }, { preserveScroll: true });
    };

    const startDownload = () => {
        const payload =
            scope === 'photo' && flickrPhotoId
                ? { flickr_photo_id: flickrPhotoId }
                : scope === 'contact' && contactNsid
                  ? { contact_nsid: contactNsid }
                  : {};

        router.post(flickrAccountPath(accountPublicId, '/download'), payload, {
            preserveScroll: true,
            onSuccess: () => onDownloadQueued?.(),
        });
    };

    const startUpload = () => {
        const payload =
            scope === 'photo' && flickrPhotoId
                ? { flickr_photo_id: flickrPhotoId }
                : scope === 'contact' && contactNsid
                  ? { contact_nsid: contactNsid }
                  : {};

        router.post(flickrAccountPath(accountPublicId, '/upload'), payload, { preserveScroll: true });
    };

    return (
        <CrawlDropdown
            onCrawl={showCrawl ? startCrawl : undefined}
            onDownload={showDownload ? startDownload : undefined}
            onUpload={showUpload ? startUpload : undefined}
            includeContactsDiscovery={scope === 'account'}
            showCrawlOptions={showCrawl}
            crawlPaused={crawlPaused}
            subjectLabel={subjectLabel}
            typeStates={typeStates}
            size={size}
            variant={variant}
            label={label ?? (showCrawl ? 'Crawl' : 'Actions')}
        />
    );
}
