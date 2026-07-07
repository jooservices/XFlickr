import { flickrPhotoThumbnailUrl } from '@/lib/flickrPhoto';
import type { Photo } from '@/types';

interface PhotoGridProps {
    photos: Photo[];
}

export default function PhotoGrid({ photos }: PhotoGridProps) {
    if (photos.length === 0) {
        return <p className="text-sm text-slate-500">No photos match the current filters.</p>;
    }

    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
            {photos.map((photo) => {
                const url = flickrPhotoThumbnailUrl(photo);

                return (
                    <figure
                        key={photo.id}
                        className="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div className="aspect-square overflow-hidden bg-slate-100 dark:bg-slate-800">
                            {url ? (
                                <img
                                    src={url}
                                    alt={photo.title || 'Photo'}
                                    className="h-full w-full object-cover"
                                    loading="lazy"
                                />
                            ) : (
                                <div className="h-full w-full bg-slate-100 dark:bg-slate-800" aria-hidden />
                            )}
                        </div>
                        <figcaption className="truncate px-2 py-1.5 text-xs text-slate-600 dark:text-slate-300">
                            {photo.title || 'Untitled'}
                        </figcaption>
                    </figure>
                );
            })}
        </div>
    );
}
