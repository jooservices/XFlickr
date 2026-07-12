import { useEffect, useState } from 'react';

import { apiGet } from '@/lib/apiClient';
import { flickrApiAccountPath } from '@/lib/flickrAccount';
import type { FlickrAccountSummary } from '@/types';

export function useFlickrTokenHealth(account: FlickrAccountSummary): boolean | null {
    const [tokenValid, setTokenValid] = useState<boolean | null>(account.token_valid ?? null);

    useEffect(() => {
        if (!account.is_connected) {
            setTokenValid(null);

            return;
        }

        const controller = new AbortController();

        void apiGet<{ data: { token_valid: boolean | null } }>(flickrApiAccountPath(account.public_id, '/token-health'), {
            signal: controller.signal,
        })
            .then((data) => setTokenValid(data.data.token_valid))
            .catch(() => undefined);

        return () => controller.abort();
    }, [account.is_connected, account.public_id]);

    return tokenValid;
}
