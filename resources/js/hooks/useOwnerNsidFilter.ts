import { useCallback, useEffect, useMemo, useState } from 'react';

import { readOwnerNsidFromUrl } from '@/lib/catalog';

export type OwnerNsidFilterKey = 'owner_nsid' | 'subject_nsid';

export function useOwnerNsidFilter(filterKey: OwnerNsidFilterKey = 'owner_nsid') {
    const [draft, setDraft] = useState('');
    const [applied, setApplied] = useState('');

    useEffect(() => {
        const fromUrl = readOwnerNsidFromUrl();
        setDraft(fromUrl);
        setApplied(fromUrl);
    }, []);

    const filters = useMemo(
        () => (applied.trim() ? { [filterKey]: applied.trim() } : {}),
        [applied, filterKey],
    );

    const apply = useCallback(() => {
        setApplied(draft);
    }, [draft]);

    const clear = useCallback(() => {
        setDraft('');
        setApplied('');
    }, []);

    const hasActiveFilter = applied.trim().length > 0;

    return {
        draft,
        setDraft,
        applied,
        filters,
        apply,
        clear,
        hasActiveFilter,
    };
}
