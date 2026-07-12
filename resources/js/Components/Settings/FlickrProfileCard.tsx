import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { type ReactNode } from 'react';

import Button from '@/Components/Button';
import CrawlActionBar from '@/Components/CrawlActionBar';
import ExpandActionBar from '@/Components/ExpandActionBar';
import ProviderCard from '@/Components/ProviderCard';
import { useFlickrTokenHealth } from '@/hooks/useFlickrTokenHealth';
import { crawlSubjectForAccount } from '@/lib/crawlSubject';
import { shortPublicId } from '@/lib/publicId';
import type { CrawlSummary, FlickrAccountSummary } from '@/types';

export interface FlickrAppSummary {
    profile: string;
    label: string | null;
    api_key_hint: string;
    callback_url: string | null;
    accounts_count: number;
}

function profileBadge(profile: string): ReactNode {
    return (
        <span className="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs font-medium text-slate-600">{profile}</span>
    );
}

function statusBadge(label: string, className: string): ReactNode {
    return <span className={`rounded px-2 py-0.5 text-xs font-medium ${className}`}>{label}</span>;
}

function publicIdBadge(publicId: string): ReactNode {
    return (
        <span
            className="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs font-medium text-slate-600"
            title={publicId}
        >
            {shortPublicId(publicId)}
        </span>
    );
}

function accountStatusBadge(account: FlickrAccountSummary): ReactNode {
    if (account.is_active) {
        return statusBadge('Active', 'bg-cyan-50 text-cyan-800');
    }

    if (account.is_connected) {
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

    return statusBadge('Disconnected', 'bg-slate-100 text-slate-600');
}

function accountBadges(account: FlickrAccountSummary, tokenValid: boolean | null): ReactNode {
    return (
        <>
            {accountStatusBadge(account)}
            {account.is_connected && tokenValid === false ? (
                statusBadge('Token invalid — Reconnect', 'bg-amber-50 text-amber-800')
            ) : null}
        </>
    );
}

interface FlickrAppProfileCardProps {
    app: FlickrAppSummary;
    defaultCallbackUrl: string;
    onDelete: () => void;
    onConnect: () => void;
}

export function FlickrAppProfileCard({ app, defaultCallbackUrl, onDelete, onConnect }: FlickrAppProfileCardProps) {
    const callbackUrl = app.callback_url ?? defaultCallbackUrl;

    return (
        <ProviderCard
            title={app.label ?? `Profile ${app.profile}`}
            subtitle="Flickr app profile"
            isConnected={false}
            onConnect={onConnect}
            extraHeaderActions={
                <Button variant="destructive" size="sm" onClick={onDelete} title="Delete app profile">
                    <Trash2 className="size-3.5" />
                    Delete
                </Button>
            }
            badges={
                <>
                    {profileBadge(app.profile)}
                    {statusBadge('Not connected', 'bg-slate-100 text-slate-600')}
                </>
            }
            quotaFallback={<p className="text-xs text-slate-500">Callback: {callbackUrl}</p>}
        >
            <p className="text-xs text-slate-500">
                App configured ({app.api_key_hint}). Connect to authorize an account.
            </p>
        </ProviderCard>
    );
}

interface FlickrAccountProfileCardProps {
    account: FlickrAccountSummary;
    app: FlickrAppSummary | null;
    rateLimit: CrawlSummary['rate_limit'] | null;
    onConnect: () => void;
    onConnectAnother?: () => void;
}

export function FlickrAccountProfileCard({
    account,
    app,
    rateLimit,
    onConnect,
    onConnectAnother,
}: FlickrAccountProfileCardProps) {
    const displayName = account.username ?? account.nsid;
    const tokenValid = useFlickrTokenHealth(account);

    return (
        <ProviderCard
            title={
                <span className="inline-flex flex-wrap items-center gap-2">
                    <span>{displayName}</span>
                    {publicIdBadge(account.public_id)}
                </span>
            }
            subtitle={account.fullname ?? undefined}
            isConnected={account.is_connected}
            onConnect={onConnect}
            onDisconnect={
                account.is_connected
                    ? () => router.post('/flickr/disconnect', { connection_key: account.nsid })
                    : undefined
            }
            rateLimit={rateLimit}
            badges={accountBadges(account, tokenValid)}
            extraHeaderActions={
                <>
                    {account.is_connected ? (
                        <CrawlActionBar
                            scope="account"
                            accountPublicId={account.public_id}
                            subjectLabel={crawlSubjectForAccount(account)}
                        />
                    ) : null}
                    {account.is_connected ? (
                        <ExpandActionBar accountPublicId={account.public_id} />
                    ) : null}
                    {account.is_connected && onConnectAnother ? (
                        <Button variant="secondary" size="sm" onClick={onConnectAnother}>
                            Connect another
                        </Button>
                    ) : null}
                </>
            }
        >
            <dl className="grid gap-1 text-xs text-slate-500 sm:grid-cols-2">
                <div>
                    <dt className="inline">NSID: </dt>
                    <dd className="inline">{account.nsid}</dd>
                </div>
                <div>
                    <dt className="inline">App profile: </dt>
                    <dd className="inline">{account.app_profile ?? 'main'}</dd>
                </div>
                <div className="sm:col-span-2">
                    <dt className="inline">ID: </dt>
                    <dd className="inline font-mono">{account.public_id}</dd>
                </div>
                <div>
                    <dt className="inline">Connected: </dt>
                    <dd className="inline">{account.connected_at ?? '—'}</dd>
                </div>
                {app ? (
                    <div>
                        <dt className="inline">API key: </dt>
                        <dd className="inline">{app.api_key_hint}</dd>
                    </div>
                ) : null}
            </dl>
        </ProviderCard>
    );
}
