import { Link } from '@inertiajs/react';
import { ExternalLink, Search, X } from 'lucide-react';
import { useMemo, useState } from 'react';

import PhotoGridTile from '@/Components/Catalog/PhotoGridTile';
import LoadingIndicator from '@/Components/ui/LoadingIndicator';
import { useInfiniteScroll } from '@/hooks/useInfiniteScroll';
import { useRemoteDataTable } from '@/hooks/useRemoteDataTable';
import { catalogOwnerUrl, photoSubtext } from '@/lib/catalog';
import { flickrPhotoGridUrl } from '@/lib/flickrPhoto';
import type { Photo } from '@/types';

const PHOTOS_PER_PAGE = 10;

interface ContactPhotoStripProps {
    ownerNsid: string;
    photosCount: number;
}

function matchesSearch(photo: Photo, query: string): boolean {
    if (query === '') {
        return true;
    }

    const haystack = (photo.title ?? '').toLowerCase();
    const needle = query.toLowerCase();

    return haystack.includes(needle) || photo.flickr_photo_id.includes(needle);
}

export default function ContactPhotoStrip({ ownerNsid, photosCount }: ContactPhotoStripProps) {
    const [searchDraft, setSearchDraft] = useState('');
    const [searchQuery, setSearchQuery] = useState('');

    const filters = useMemo(() => ({ owner_nsid: ownerNsid }), [ownerNsid]);

    const { data: photos, loading, loadingMore, hasMore, loadMore, meta } = useRemoteDataTable<Photo>({
        fetchPath: '/api/v1/flickr/catalog/photos',
        filters,
        perPage: PHOTOS_PER_PAGE,
        paginationMode: 'append',
        initialSort: 'id',
        initialDirection: 'desc',
    });

    const filteredPhotos = useMemo(
        () => photos.filter((photo) => matchesSearch(photo, searchQuery)),
        [photos, searchQuery],
    );

    const sentinelRef = useInfiniteScroll({
        hasMore: hasMore && searchQuery === '',
        loading: loadingMore,
        onLoadMore: loadMore,
    });

    const loadedTotal = meta?.total ?? photosCount;
    const showingCount = searchQuery === '' ? photos.length : filteredPhotos.length;

    if (photosCount === 0) {
        return (
            <p className="text-sm text-slate-500">
                No photos indexed for this contact yet. Crawl photos from the contact page or catalog.
            </p>
        );
    }

    return (
        <section className="flex min-h-0 flex-1 flex-col gap-3">
            <div className="flex shrink-0 items-center justify-between gap-2">
                <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-500">Photos</h3>
                <Link
                    href={catalogOwnerUrl('/photos', ownerNsid)}
                    className="inline-flex items-center gap-1 text-xs text-cyan-700 hover:underline"
                >
                    View all
                    <ExternalLink className="h-3 w-3" />
                </Link>
            </div>

            <form
                className="relative shrink-0"
                onSubmit={(event) => {
                    event.preventDefault();
                    setSearchQuery(searchDraft.trim());
                }}
            >
                <Search className="pointer-events-none absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                <input
                    type="search"
                    value={searchDraft}
                    onChange={(event) => setSearchDraft(event.target.value)}
                    placeholder="Search loaded titles…"
                    className="w-full rounded-md border border-slate-200 py-1.5 pr-8 pl-8 text-sm"
                />
                {searchDraft ? (
                    <button
                        type="button"
                        className="absolute top-1/2 right-2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:text-slate-600"
                        aria-label="Clear search"
                        onClick={() => {
                            setSearchDraft('');
                            setSearchQuery('');
                        }}
                    >
                        <X className="h-3.5 w-3.5" />
                    </button>
                ) : null}
            </form>

            {loading && photos.length === 0 ? (
                <LoadingIndicator size="sm" label="Loading photos…" className="flex-1" />
            ) : filteredPhotos.length === 0 ? (
                <p className="text-sm text-slate-500">
                    {searchQuery ? 'No loaded photos match your search.' : 'No photos found.'}
                </p>
            ) : (
                <div
                    className="grid min-h-64 flex-1 grid-cols-2 gap-3 overflow-y-auto sm:min-h-72 sm:grid-cols-3 lg:min-h-[28rem]"
                    onWheel={(event) => event.stopPropagation()}
                >
                    {filteredPhotos.map((photo) => (
                        <PhotoGridTile
                            key={photo.id}
                            imageUrl={flickrPhotoGridUrl(photo)}
                            alt={photo.title || 'Photo'}
                            revealBottomRowOnHover
                            bottomRow={
                                <span className="truncate text-xs text-white drop-shadow">
                                    {photoSubtext(photo.title)}
                                </span>
                            }
                            onClick={() => {
                                window.open(
                                    `https://www.flickr.com/photos/${encodeURIComponent(ownerNsid)}/${encodeURIComponent(photo.flickr_photo_id)}`,
                                    '_blank',
                                    'noopener,noreferrer',
                                );
                            }}
                        />
                    ))}

                    {searchQuery === '' && hasMore ? <div ref={sentinelRef} className="h-2" aria-hidden /> : null}
                </div>
            )}

            {loadingMore ? (
                <div className="flex shrink-0 justify-center">
                    <LoadingIndicator size="sm" label="Loading more…" />
                </div>
            ) : null}

            {!loading || photos.length > 0 ? (
                <p className="shrink-0 text-center text-[11px] text-slate-400">
                    {searchQuery === ''
                        ? `Showing ${showingCount.toLocaleString()} of ${loadedTotal.toLocaleString()} photos`
                        : `${filteredPhotos.length.toLocaleString()} match${filteredPhotos.length === 1 ? '' : 'es'} in loaded photos`}
                </p>
            ) : null}

            {searchQuery !== '' ? (
                <p className="shrink-0 text-[11px] text-slate-400">Search filters photos already loaded in this panel.</p>
            ) : null}
        </section>
    );
}
