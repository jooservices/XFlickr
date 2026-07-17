import { useCallback, useMemo } from 'react';

import { usePolledResource } from '@/hooks/usePolledResource';
import type {
    ActivityFeedEntry,
    ActivityFeedFilters,
    ActivityFeedMeta,
    ActivityFeedRange,
} from '@/lib/activityFeed';
import type { ApiEnvelope } from '@/lib/apiClient';

type ActivityFeedResponse = ApiEnvelope<ActivityFeedEntry[], ActivityFeedMeta>;

const POLL_MS = 15_000;
const DEFAULT_PER_PAGE = 15;

function readSearchParams(): URLSearchParams {
    if (typeof window === 'undefined') {
        return new URLSearchParams();
    }

    return new URLSearchParams(window.location.search);
}

function rangeToFrom(range: ActivityFeedRange): string {
    const now = Date.now();
    const offsetMs =
        range === '7d' ? 7 * 24 * 60 * 60 * 1000 : range === '30d' ? 30 * 24 * 60 * 60 * 1000 : 24 * 60 * 60 * 1000;

    return new Date(now - offsetMs).toISOString();
}

function detectRange(from: string): ActivityFeedRange {
    if (!from) {
        return 'all';
    }

    const ageMs = Date.now() - Date.parse(from);
    if (!Number.isFinite(ageMs)) {
        return '24h';
    }

    const day = 24 * 60 * 60 * 1000;
    if (ageMs <= day * 1.5) {
        return '24h';
    }
    if (ageMs <= day * 8) {
        return '7d';
    }
    if (ageMs <= day * 32) {
        return '30d';
    }

    return 'all';
}

export function readActivityFeedFiltersFromLocation(): ActivityFeedFilters {
    const params = readSearchParams();

    return {
        type: params.get('type') ?? '',
        level: params.get('level') ?? '',
        action_prefix: params.get('action_prefix') ?? '',
        correlation_id: params.get('correlation_id') ?? '',
        from: params.get('from') ?? rangeToFrom('24h'),
        to: params.get('to') ?? '',
        page: Math.max(1, Number(params.get('page') ?? '1') || 1),
        per_page: Math.min(50, Math.max(1, Number(params.get('per_page') ?? String(DEFAULT_PER_PAGE)) || DEFAULT_PER_PAGE)),
    };
}

export function writeActivityFeedFiltersToLocation(filters: ActivityFeedFilters): void {
    if (typeof window === 'undefined') {
        return;
    }

    const params = new URLSearchParams();
    if (filters.type) {
        params.set('type', filters.type);
    }
    if (filters.level) {
        params.set('level', filters.level);
    }
    if (filters.action_prefix) {
        params.set('action_prefix', filters.action_prefix);
    }
    if (filters.correlation_id) {
        params.set('correlation_id', filters.correlation_id);
    }
    if (filters.from) {
        params.set('from', filters.from);
    }
    if (filters.to) {
        params.set('to', filters.to);
    }
    if (filters.page > 1) {
        params.set('page', String(filters.page));
    }
    if (filters.per_page !== DEFAULT_PER_PAGE) {
        params.set('per_page', String(filters.per_page));
    }

    const query = params.toString();
    const next = query ? `${window.location.pathname}?${query}` : window.location.pathname;
    window.history.replaceState({}, '', next);
}

export function useActivityFeed(filters: ActivityFeedFilters) {
    const params = useMemo(
        () => ({
            type: filters.type || undefined,
            level: filters.level || undefined,
            action_prefix: filters.action_prefix || undefined,
            correlation_id: filters.correlation_id || undefined,
            from: filters.from || undefined,
            to: filters.to || undefined,
            page: filters.page,
            per_page: filters.per_page,
        }),
        [filters],
    );

    const { data, error, loading, refresh } = usePolledResource<ActivityFeedResponse>('/api/v1/operations/activities', {
        intervalMs: POLL_MS,
        params,
    });

    const entries = data?.data ?? [];
    const meta = data?.meta ?? null;
    const range = detectRange(filters.from);

    return {
        entries,
        meta,
        loading,
        error,
        refresh,
        range,
    };
}

export function applyActivityFeedRange(range: ActivityFeedRange, filters: ActivityFeedFilters): ActivityFeedFilters {
    if (range === 'all') {
        return { ...filters, from: '', to: '', page: 1 };
    }

    return { ...filters, from: rangeToFrom(range), to: '', page: 1 };
}

export function useActivityFeedFilterState(
    filters: ActivityFeedFilters,
    setFilters: (next: ActivityFeedFilters) => void,
) {
    const update = useCallback(
        (patch: Partial<ActivityFeedFilters>) => {
            const next = { ...filters, ...patch, page: patch.page ?? 1 };
            writeActivityFeedFiltersToLocation(next);
            setFilters(next);
        },
        [filters, setFilters],
    );

    return { update };
}
