import { Link, router } from '@inertiajs/react';

import CrawlActionBar from '@/Components/Flickr/CrawlActionBar';
import ExpandActionBar from '@/Components/Flickr/ExpandActionBar';
import FlickrAccountCardFooter from '@/Components/Flickr/FlickrAccountCardFooter';
import Button from '@/Components/ui/Button';
import ProviderCard from '@/Components/ui/ProviderCard';
import { useFlickrCrawlSummary } from '@/hooks/useFlickrCrawlSummary';
import { useFlickrTokenHealth } from '@/hooks/useFlickrTokenHealth';
import { cn } from '@/lib/cn';
import { crawlSubjectForAccount } from '@/lib/crawlSubject';
import { flickrAccountPath } from '@/lib/flickrAccount';
import { shortPublicId } from '@/lib/publicId';
import type { FlickrAccountSummary } from '@/types';

const NAV_LINKS = [
    { label: 'Contacts', suffix: '/contacts' },
    { label: 'Photos', suffix: '/photos' },
    { label: 'Photosets', suffix: '/photosets' },
    { label: 'Galleries', suffix: '/galleries' },
] as const;

interface AccountOpsCardProps {
    account: FlickrAccountSummary;
    onReconnect?: () => void;
}

function statusBadge(label: string, className: string) {
    return <span className={`rounded px-2 py-0.5 text-xs font-medium ${className}`}>{label}</span>;
}

function accountStatusBadge(account: FlickrAccountSummary, tokenValid: boolean | null) {
    return (
        <>
            {account.is_active ? (
                statusBadge('Active', 'bg-cyan-50 text-cyan-800')
            ) : (
                <Button
                    variant="link"
                    size="xs"
                    onClick={() => router.post('/flickr/activate', { connection_key: account.nsid })}
                    className="px-0"
                >
                    Set active
                </Button>
            )}
            {account.is_connected && tokenValid === false
                ? statusBadge('Token invalid — Reconnect', 'bg-amber-50 text-amber-800')
                : null}
        </>
    );
}

export default function AccountOpsCard({ account, onReconnect }: AccountOpsCardProps) {
    const displayName = account.username ?? account.nsid;
    const tokenValid = useFlickrTokenHealth(account);
    const summary = useFlickrCrawlSummary(account.public_id, account.is_connected !== false);

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
            isConnected={account.is_connected}
            onConnect={onReconnect}
            onDisconnect={
                account.is_connected
                    ? () => router.post('/flickr/disconnect', { connection_key: account.nsid })
                    : undefined
            }
            badges={accountStatusBadge(account, tokenValid)}
            footer={
                summary ? (
                    <FlickrAccountCardFooter summary={summary} />
                ) : (
                    <p className="text-xs text-slate-400">Loading quota…</p>
                )
            }
            extraHeaderActions={
                account.is_connected ? (
                    <>
                        <CrawlActionBar
                            scope="account"
                            accountPublicId={account.public_id}
                            subjectLabel={crawlSubjectForAccount(account)}
                        />
                        <ExpandActionBar accountPublicId={account.public_id} />
                    </>
                ) : null
            }
        >
            <dl className="mb-3 grid gap-1 text-xs text-slate-500 sm:grid-cols-2">
                <div>
                    <dt className="inline">NSID: </dt>
                    <dd className="inline">{account.nsid}</dd>
                </div>
                <div>
                    <dt className="inline">App profile: </dt>
                    <dd className="inline">{account.app_profile ?? 'main'}</dd>
                </div>
            </dl>
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
