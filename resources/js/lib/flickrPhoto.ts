export type FlickrPhotoStaticSize = 's' | 'q' | 't' | 'm' | 'n' | 'z' | 'c' | 'b';

type FlickrPhotoStaticFields = {
    flickr_photo_id: string;
    secret: string | null;
    server: string | null;
};

export function flickrPhotoStaticUrl(
    photo: FlickrPhotoStaticFields,
    size: FlickrPhotoStaticSize = 'q',
): string | null {
    const { flickr_photo_id: photoId, secret, server } = photo;

    if (photoId === '' || secret === null || secret === '' || server === null || server === '') {
        return null;
    }

    return `https://live.staticflickr.com/${server}/${photoId}_${secret}_${size}.jpg`;
}

/** 150px square — compact table cells and badges. */
export function flickrPhotoThumbnailUrl(photo: FlickrPhotoStaticFields): string | null {
    return flickrPhotoStaticUrl(photo, 'q');
}

/** 320px long edge — catalog grid tiles. */
export function flickrPhotoGridUrl(photo: FlickrPhotoStaticFields): string | null {
    return flickrPhotoStaticUrl(photo, 'n');
}

/** 640px long edge — in-app photo detail preview. */
export function flickrPhotoPreviewUrl(photo: FlickrPhotoStaticFields): string | null {
    return flickrPhotoStaticUrl(photo, 'z');
}
