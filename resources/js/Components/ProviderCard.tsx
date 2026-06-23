import { Link2, Unlink } from 'lucide-react';
import type { ReactNode } from 'react';

import Button from '@/Components/Button';
import Card from '@/Components/Card';
import RateLimitMeter from '@/Components/RateLimitMeter';
import type { RateLimitState } from '@/types';

export interface ProviderCardProps {
    title: string;
    subtitle?: string | null;
    isConnected: boolean;
    onConnect?: () => void;
    onDisconnect?: () => void;
    rateLimit?: RateLimitState | null;
    quotaFallback?: ReactNode;
    badges?: ReactNode;
    extraHeaderActions?: ReactNode;
    children?: ReactNode;
    className?: string;
}

export default function ProviderCard({
    title,
    subtitle,
    isConnected,
    onConnect,
    onDisconnect,
    rateLimit,
    quotaFallback,
    badges,
    extraHeaderActions,
    children,
    className,
}: ProviderCardProps) {
    const headerActions = (
        <>
            {extraHeaderActions}
            {!isConnected && onConnect ? (
                <Button variant="connect" size="sm" onClick={onConnect} title="Connect">
                    <Link2 className="size-3.5" />
                    Connect
                </Button>
            ) : null}
            {isConnected && onDisconnect ? (
                <Button variant="destructive" size="sm" onClick={onDisconnect} title="Disconnect">
                    <Unlink className="size-3.5" />
                    Disconnect
                </Button>
            ) : null}
        </>
    );

    const footer = rateLimit ? (
        <RateLimitMeter rateLimit={rateLimit} compact />
    ) : (
        quotaFallback ?? <p className="text-xs text-slate-400">No quota data</p>
    );

    return (
        <Card
            title={title}
            subtitle={subtitle ?? undefined}
            badges={badges}
            headerActions={headerActions}
            footer={footer}
            className={className}
        >
            {children}
        </Card>
    );
}
