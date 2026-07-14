import { Link } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import Button from '@/Components/Button';
import {
    useInvalidFlickrTokenAccounts,
    type InvalidFlickrTokenAccount,
} from '@/hooks/useInvalidFlickrTokenAccounts';
import { connectionsPath } from '@/lib/connections';
import {
    dismissFlickrTokenHealthAccounts,
    filterUndismissedInvalidAccounts,
    getDismissedFlickrTokenHealthAccounts,
} from '@/lib/flickrTokenHealthBannerDismiss';
import type { FlickrAccount } from '@/types';

interface TokenHealthBannerProps {
    accounts: FlickrAccount[];
    onVisibleChange?: (visible: boolean) => void;
}

function formatAccountList(accounts: InvalidFlickrTokenAccount[]): string {
    if (accounts.length === 1) {
        return accounts[0].label;
    }

    if (accounts.length === 2) {
        return `${accounts[0].label} and ${accounts[1].label}`;
    }

    const head = accounts
        .slice(0, -1)
        .map((account) => account.label)
        .join(', ');

    return `${head}, and ${accounts[accounts.length - 1].label}`;
}

export default function TokenHealthBanner({ accounts, onVisibleChange }: TokenHealthBannerProps) {
    const { invalidAccounts } = useInvalidFlickrTokenAccounts(accounts);
    const [dismissedIds, setDismissedIds] = useState(() => getDismissedFlickrTokenHealthAccounts());
    const visibleInvalid = useMemo(
        () => filterUndismissedInvalidAccounts(invalidAccounts, dismissedIds),
        [dismissedIds, invalidAccounts],
    );

    useEffect(() => {
        onVisibleChange?.(visibleInvalid.length > 0);
    }, [onVisibleChange, visibleInvalid.length]);

    if (visibleInvalid.length === 0) {
        return null;
    }

    const handleDismiss = () => {
        const publicIds = visibleInvalid.map((account) => account.public_id);

        dismissFlickrTokenHealthAccounts(publicIds);
        setDismissedIds((current) => {
            const next = new Set(current);

            for (const publicId of publicIds) {
                next.add(publicId);
            }

            return next;
        });
    };

    const accountPhrase =
        visibleInvalid.length === 1 ? 'account needs' : `${visibleInvalid.length} accounts need`;

    return (
        <div className="border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-950">
            <div className="mx-auto flex max-w-6xl items-center justify-center gap-3">
                <p className="text-center font-medium">
                    Flickr token invalid — {formatAccountList(visibleInvalid)} {accountPhrase} reconnecting
                    on{' '}
                    <Link href={connectionsPath()} className="font-semibold text-amber-900 underline">
                        Connections
                    </Link>
                    .
                </p>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={handleDismiss}
                    className="shrink-0 text-amber-800 hover:bg-amber-100"
                    aria-label="Dismiss token health warning"
                    title="Dismiss for this session"
                >
                    <X className="size-4" />
                </Button>
            </div>
        </div>
    );
}
