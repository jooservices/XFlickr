export function flickrAccountKey(publicId: string): string {
    return publicId;
}

export function flickrContactSegment(contactNsid: string): string {
    return encodeURIComponent(contactNsid);
}

export function flickrAccountPath(publicId: string, suffix = ''): string {
    return `/flickr/accounts/${flickrAccountKey(publicId)}${suffix}`;
}

export function flickrApiAccountPath(publicId: string, suffix = ''): string {
    return `/api/flickr/accounts/${flickrAccountKey(publicId)}${suffix}`;
}

export function flickrContactPath(publicId: string, contactNsid: string, suffix = ''): string {
    return flickrAccountPath(publicId, `/contacts/${flickrContactSegment(contactNsid)}${suffix}`);
}
