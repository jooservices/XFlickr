import { Trash2 } from 'lucide-react';
import { type ReactNode } from 'react';

import Button from '@/Components/ui/Button';
import ProviderCard from '@/Components/ui/ProviderCard';

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
                    {statusBadge('App ready', 'bg-slate-100 text-slate-600')}
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
