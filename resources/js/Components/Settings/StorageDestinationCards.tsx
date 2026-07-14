import { router } from '@inertiajs/react';

import Button from '@/Components/Button';
import ProviderCard from '@/Components/ProviderCard';
import type { StorageAppSummary } from '@/Components/Settings/storageCredentialsTypes';
import StorageReauthorizeBanner from '@/Components/Storage/StorageReauthorizeBanner';
import { buttonVariants } from '@/lib/buttonVariants';
import type { StorageAccount } from '@/types';

interface StorageDestinationCardsProps {
    oauthApps: StorageAppSummary[];
    oauthAccounts: StorageAccount[];
    r2Accounts: StorageAccount[];
    unconnectedApps: StorageAppSummary[];
    accountsNeedingReauth: StorageAccount[];
    hasAnyAccounts: boolean;
    connectionsReturnUrl: string;
    providerLabel: (value: string) => string;
    onOpenPicker: () => void;
    onConnectOAuth: (provider: string) => void;
}

export default function StorageDestinationCards({
    oauthApps,
    oauthAccounts,
    r2Accounts,
    unconnectedApps,
    accountsNeedingReauth,
    hasAnyAccounts,
    connectionsReturnUrl,
    providerLabel,
    onOpenPicker,
    onConnectOAuth,
}: StorageDestinationCardsProps) {
    return (
        <>
            {accountsNeedingReauth.length > 0 ? (
                <div className="space-y-3">
                    {accountsNeedingReauth.map((account) => (
                        <StorageReauthorizeBanner
                            key={account.id}
                            account={account}
                            returnUrl={connectionsReturnUrl}
                        />
                    ))}
                </div>
            ) : null}

            {!hasAnyAccounts && unconnectedApps.length === 0 ? (
                <p className="text-sm text-slate-600">
                    No storage destinations yet.{' '}
                    <button type="button" className="text-cyan-700 hover:underline" onClick={onOpenPicker}>
                        Add a destination
                    </button>
                </p>
            ) : (
                <div className="grid gap-4">
                    {oauthAccounts.map((account) => {
                        const app = oauthApps.find((item) => item.provider === account.provider);

                        return (
                            <ProviderCard
                                key={account.id}
                                title={account.label}
                                subtitle={providerLabel(account.provider)}
                                isConnected
                                onDisconnect={() =>
                                    router.post('/storage/disconnect', { account_id: account.id })
                                }
                                badges={
                                    <>
                                        {account.is_default ? (
                                            <span className="rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800">
                                                Default
                                            </span>
                                        ) : null}
                                    </>
                                }
                                extraHeaderActions={
                                    <>
                                        {account.needs_reauthorization ? (
                                            <a
                                                href={`${account.reauthorize_url}?return_url=${encodeURIComponent(connectionsReturnUrl)}`}
                                                className={buttonVariants({ variant: 'warning', size: 'sm' })}
                                            >
                                                Reauthorize
                                            </a>
                                        ) : null}
                                        {!account.is_default ? (
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                onClick={() =>
                                                    router.post('/storage/set-default', {
                                                        account_id: account.id,
                                                    })
                                                }
                                            >
                                                Set default
                                            </Button>
                                        ) : null}
                                    </>
                                }
                            >
                                <dl className="grid gap-1 text-xs text-slate-500 sm:grid-cols-2">
                                    <div>
                                        <dt className="inline">Connected: </dt>
                                        <dd className="inline">{account.connected_at ?? '—'}</dd>
                                    </div>
                                    <div>
                                        <dt className="inline">Credentials: </dt>
                                        <dd className="inline">{app?.client_id_hint ?? '—'}</dd>
                                    </div>
                                </dl>
                            </ProviderCard>
                        );
                    })}

                    {unconnectedApps.map((app) => (
                        <ProviderCard
                            key={`connect-${app.provider}`}
                            title={app.label}
                            subtitle={providerLabel(app.provider)}
                            isConnected={false}
                            onConnect={() => onConnectOAuth(app.provider)}
                            badges={
                                <span className="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                    Ready to connect
                                </span>
                            }
                        >
                            <p className="text-xs text-slate-500">
                                Credentials saved ({app.client_id_hint}). Connect to authorize an account.
                            </p>
                        </ProviderCard>
                    ))}

                    {r2Accounts.map((account) => (
                        <ProviderCard
                            key={account.id}
                            title={account.label}
                            subtitle={providerLabel(account.provider)}
                            isConnected
                            onDisconnect={() =>
                                router.post('/storage/disconnect', { account_id: account.id })
                            }
                            badges={
                                account.is_default ? (
                                    <span className="rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800">
                                        Default
                                    </span>
                                ) : null
                            }
                            extraHeaderActions={
                                !account.is_default ? (
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={() =>
                                            router.post('/storage/set-default', {
                                                account_id: account.id,
                                            })
                                        }
                                    >
                                        Set default
                                    </Button>
                                ) : null
                            }
                        >
                            <dl className="grid gap-1 text-xs text-slate-500 sm:grid-cols-2">
                                <div>
                                    <dt className="inline">Connected: </dt>
                                    <dd className="inline">{account.connected_at ?? '—'}</dd>
                                </div>
                                {account.connection_meta ? (
                                    <>
                                        <div>
                                            <dt className="inline">Bucket: </dt>
                                            <dd className="inline">{account.connection_meta.bucket ?? '—'}</dd>
                                        </div>
                                        <div className="sm:col-span-2">
                                            <dt className="inline">Endpoint: </dt>
                                            <dd className="inline break-all">
                                                {account.connection_meta.endpoint ?? '—'}
                                            </dd>
                                        </div>
                                        {account.connection_meta.prefix ? (
                                            <div>
                                                <dt className="inline">Prefix: </dt>
                                                <dd className="inline">{account.connection_meta.prefix}</dd>
                                            </div>
                                        ) : null}
                                    </>
                                ) : null}
                            </dl>
                        </ProviderCard>
                    ))}
                </div>
            )}
        </>
    );
}
