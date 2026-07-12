export function shortPublicId(publicId: string, length = 8): string {
    return publicId.slice(0, length);
}
