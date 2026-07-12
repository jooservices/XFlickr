export const API_V1 = '/api/v1';

export function apiV1Path(suffix: string): string {
    const normalized = suffix.startsWith('/') ? suffix : `/${suffix}`;

    return `${API_V1}${normalized}`;
}

export function flickrApiV1AccountPath(publicId: string, suffix = ''): string {
    const normalized = suffix === '' || suffix.startsWith('/') ? suffix : `/${suffix}`;

    return apiV1Path(`/flickr/accounts/${publicId}${normalized}`);
}
