import { useEffect, useMemo, useRef, useState } from 'react';

import { flickrAccountLabel } from '@/hooks/useFlickrRateLimit';
import { apiGet } from '@/lib/apiClient';
import { flickrApiAccountPath } from '@/lib/flickrAccount';
import type { FlickrAccount } from '@/types';

export type InvalidFlickrTokenAccount = {
    public_id: string;
    label: string;
};

function connectedAccounts(accounts: FlickrAccount[] | undefined): FlickrAccount[] {
    return (accounts ?? []).filter((account) => account.disconnected_at === null);
}

function accountProbeKey(accounts: FlickrAccount[] | undefined): string {
    return connectedAccounts(accounts)
        .map((account) => account.public_id)
        .sort()
        .join(',');
}

export function useInvalidFlickrTokenAccounts(accounts: FlickrAccount[] | undefined): {
    invalidAccounts: InvalidFlickrTokenAccount[];
    probing: boolean;
} {
    const [invalidAccounts, setInvalidAccounts] = useState<InvalidFlickrTokenAccount[]>([]);
    const [probing, setProbing] = useState(false);
    const accountKey = useMemo(() => accountProbeKey(accounts), [accounts]);
    const targetsRef = useRef<{ key: string; targets: FlickrAccount[] }>({
        key: '',
        targets: [],
    });

    if (targetsRef.current.key !== accountKey) {
        targetsRef.current = {
            key: accountKey,
            targets: connectedAccounts(accounts),
        };
    }

    useEffect(() => {
        const targets = targetsRef.current.targets;

        if (targets.length === 0) {
            setInvalidAccounts([]);
            setProbing(false);

            return;
        }

        const controller = new AbortController();

        setProbing(true);

        void Promise.all(
            targets.map(async (account) => {
                try {
                    const data = await apiGet<{ data: { token_valid: boolean | null } }>(
                        flickrApiAccountPath(account.public_id, '/token-health'),
                        { signal: controller.signal },
                    );

                    if (data.data.token_valid === false) {
                        return {
                            public_id: account.public_id,
                            label: flickrAccountLabel(account),
                        };
                    }
                } catch {
                    return null;
                }

                return null;
            }),
        ).then((results) => {
            if (controller.signal.aborted) {
                return;
            }

            setInvalidAccounts(
                results.filter((result): result is InvalidFlickrTokenAccount => result !== null),
            );
            setProbing(false);
        });

        return () => controller.abort();
    }, [accountKey]);

    return { invalidAccounts, probing };
}
