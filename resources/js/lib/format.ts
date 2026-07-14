export function formatCount(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return value.toLocaleString();
}

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

    if (size < 1024 * 1024 * 1024) {
        return `${(size / (1024 * 1024)).toFixed(1)} MB`;
    }

    if (size < 1024 * 1024 * 1024 * 1024) {
        return `${(size / (1024 * 1024 * 1024)).toFixed(1)} GB`;
    }

    return `${(size / (1024 * 1024 * 1024 * 1024)).toFixed(1)} TB`;
}

export function formatSyncedAt(value: string | null | undefined): string | null {
    if (!value) {
        return null;
    }

    return new Date(value).toLocaleString();
}

export function formatRelativeTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const then = new Date(value).getTime();
    if (Number.isNaN(then)) {
        return value;
    }

    const seconds = Math.max(0, Math.round((Date.now() - then) / 1000));
    if (seconds < 60) {
        return `${seconds}s ago`;
    }

    const minutes = Math.round(seconds / 60);
    if (minutes < 60) {
        return `${minutes}m ago`;
    }

    const hours = Math.round(minutes / 60);
    if (hours < 48) {
        return `${hours}h ago`;
    }

    return new Date(value).toLocaleString();
}
