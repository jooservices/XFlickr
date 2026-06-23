export function flickrPhotoThumbnailUrl(photo: {
    flickr_photo_id: string;
    secret: string | null;
    server: string | null;
}): string | null {
    const { flickr_photo_id: photoId, secret, server } = photo;

    if (photoId === '' || secret === null || secret === '' || server === null || server === '') {
        return null;
    }

    return `https://live.staticflickr.com/${server}/${photoId}_${secret}_q.jpg`;
}
