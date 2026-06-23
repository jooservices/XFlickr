export function flickrCollectionThumbnailUrl(item: {
    primary_photo_id?: string | null;
    primary_secret?: string | null;
    primary_server?: string | null;
}): string | null {
    const photoId = item.primary_photo_id?.trim() ?? '';
    const secret = item.primary_secret?.trim() ?? '';
    const server = item.primary_server?.trim() ?? '';

    if (photoId === '' || secret === '' || server === '') {
        return null;
    }

    return `https://live.staticflickr.com/${server}/${photoId}_${secret}_q.jpg`;
}
