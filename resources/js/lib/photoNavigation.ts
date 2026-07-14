import type { Photo } from '@/types';

export function findPhotoIndex(photos: Photo[], photo: Photo): number {
    return photos.findIndex((candidate) => candidate.id === photo.id);
}

export function adjacentPhotoIndex(
    photos: Photo[],
    currentPhoto: Photo,
    direction: -1 | 1,
): number | null {
    const currentIndex = findPhotoIndex(photos, currentPhoto);
    if (currentIndex === -1) {
        return null;
    }

    const nextIndex = currentIndex + direction;
    if (nextIndex < 0 || nextIndex >= photos.length) {
        return null;
    }

    return nextIndex;
}

export function shouldIgnorePhotoModalShortcut(event: KeyboardEvent): boolean {
    if (event.metaKey || event.ctrlKey || event.altKey) {
        return true;
    }

    const target = event.target;
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    const tag = target.tagName;
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target.isContentEditable;
}
