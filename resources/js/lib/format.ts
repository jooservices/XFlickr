export function formatBytes(size: number | null): string {
    if (size === null) {
        return '—';
    }

    if (size < 1024) {
        return `${size} B`;
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

export function formatSyncedAt(value: string | null | undefined): string | null {
    if (!value) {
        return null;
    }

    return new Date(value).toLocaleString();
}
