import { Link, router } from '@inertiajs/react';

import Button from '@/Components/Button';
import FlickrAccountCardFooter from '@/Components/Flickr/FlickrAccountCardFooter';
import CrawlActionBar from '@/Components/macros/CrawlActionBar';
import ExpandActionBar from '@/Components/macros/ExpandActionBar';
import ProviderCard from '@/Components/ProviderCard';
import { cn } from '@/lib/cn';
import { crawlSubjectForAccount } from '@/lib/crawlSubject';
import { flickrAccountPath } from '@/lib/flickrAccount';
import { shortPublicId } from '@/lib/publicId';
import type { CrawlSummary, FlickrAccountSummary } from '@/types';

const NAV_LINKS = [
    { label: 'Contacts', suffix: '/contacts' },
    { label: 'Photos', suffix: '/photos' },
    { label: 'Photosets', suffix: '/photosets' },
    { label: 'Galleries', suffix: '/galleries' },
] as const;

interface AccountOpsCardProps {
    account: FlickrAccountSummary;
    summary: CrawlSummary | null;
}

function accountStatusBadge(account: FlickrAccountSummary) {
    if (account.is_active) {
        return (
            <span className="rounded bg-cyan-50 px-2 py-0.5 text-xs font-medium text-cyan-800">Active</span>
        );
    }

    return (
        <Button
            variant="link"
            size="xs"
            onClick={() => router.post('/flickr/activate', { connection_key: account.nsid })}
            className="px-0"
        >
            Set active
        </Button>
    );
}

export default function AccountOpsCard({ account, summary }: AccountOpsCardProps) {
    const displayName = account.username ?? account.nsid;

    return (
        <ProviderCard
            className={cn(summary && summary.runs.running > 0 && 'border-cyan-200')}
            title={
                <span className="inline-flex flex-wrap items-center gap-2">
                    <Link href={flickrAccountPath(account.public_id)} className="hover:underline">
                        {displayName}
                    </Link>
                    <span
                        className="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs font-medium text-slate-600"
                        title={account.public_id}
                    >
                        {shortPublicId(account.public_id)}
                    </span>
                </span>
            }
            subtitle={account.fullname ?? undefined}
            isConnected
            badges={accountStatusBadge(account)}
            footer={
                summary ? (
                    <FlickrAccountCardFooter summary={summary} />
                ) : (
                    <p className="text-xs text-slate-400">Loading quota…</p>
                )
            }
            extraHeaderActions={
                <>
                    <CrawlActionBar
                        scope="account"
                        accountPublicId={account.public_id}
                        subjectLabel={crawlSubjectForAccount(account)}
                    />
                    <ExpandActionBar accountPublicId={account.public_id} />
                </>
            }
        >
            <nav className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                {NAV_LINKS.map((link) => (
                    <Link
                        key={link.suffix}
                        href={flickrAccountPath(account.public_id, link.suffix)}
                        className="font-medium text-cyan-700 hover:underline"
                    >
                        {link.label}
                    </Link>
                ))}
            </nav>
        </ProviderCard>
    );
}
