import { router, useForm } from '@inertiajs/react';
import { X } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

import Button from '@/Components/Button';
import ProviderCard from '@/Components/ProviderCard';
import StorageReauthorizeBanner from '@/Components/Storage/StorageReauthorizeBanner';
import { buttonVariants } from '@/lib/buttonVariants';
import type { StorageAccount } from '@/types';

interface StorageAppSummary {
    provider: string;
    label: string;
    client_id_hint: string;
    redirect: string | null;
    accounts_count: number;
}

interface StorageDriverOption {
    value: string;
    label: string;
    requires_oauth: boolean;
    requires_app: boolean;
    requires_account: boolean;
}

interface StorageCredentialsPanelProps {
    apps: StorageAppSummary[];
    accounts: StorageAccount[];
    redirects: Record<string, string>;
    drivers: StorageDriverOption[];
}

const addButtonClass = buttonVariants({ variant: 'secondary', size: 'md' });

export default function StorageCredentialsPanel({ apps, accounts, redirects, drivers }: StorageCredentialsPanelProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [r2DialogOpen, setR2DialogOpen] = useState(false);

    const form = useForm({
        provider: 'google_photos',
        label: '',
        client_id: '',
        client_secret: '',
        redirect: redirects.google_photos ?? '',
    });

    const r2Form = useForm({
        label: '',
        access_key_id: '',
        secret_access_key: '',
        bucket: '',
        endpoint: '',
        region: 'auto',
        prefix: '',
    });

    const oauthApps = apps.filter((app) => app.provider !== 'r2');
    const configuredOAuthProviders = useMemo(
        () => new Set(oauthApps.map((app) => app.provider)),
        [oauthApps],
    );
    const accountsNeedingReauth = accounts.filter((account) => account.needs_reauthorization);
    const r2Accounts = accounts.filter((account) => account.provider === 'r2');

    const providerLabel = (value: string) => drivers.find((driver) => driver.value === value)?.label ?? value;

    const openCreate = (provider: string) => {
        form.setData({
            provider,
            label: '',
            client_id: '',
            client_secret: '',
            redirect: redirects[provider] ?? '',
        });
        form.clearErrors();
        setDialogOpen(true);
    };

    const openAddR2 = () => {
        r2Form.clearErrors();
        r2Form.reset();
        setR2DialogOpen(true);
    };

    const saveApp = (event: FormEvent) => {
        event.preventDefault();
        form.post('/settings/storage-app', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setDialogOpen(false);
            },
        });
    };

    const connectR2 = (event: FormEvent) => {
        event.preventDefault();
        r2Form.post('/storage/connect/r2', {
            preserveScroll: true,
            onSuccess: () => {
                r2Form.reset();
                setR2DialogOpen(false);
            },
        });
    };

    const startOAuth = (provider: string) => {
        window.location.href = `/storage/oauth/${encodeURIComponent(provider)}`;
    };

    const hasAnyCards = oauthApps.length > 0 || r2Accounts.length > 0;

    const addButtons = (
        <div className="flex flex-wrap gap-2">
            {!configuredOAuthProviders.has('google_photos') ? (
                <button type="button" onClick={() => openCreate('google_photos')} className={addButtonClass}>
                    Add Google Photos
                </button>
            ) : null}
            {!configuredOAuthProviders.has('google') ? (
                <button type="button" onClick={() => openCreate('google')} className={addButtonClass}>
                    Add Google Drive
                </button>
            ) : null}
            {!configuredOAuthProviders.has('onedrive') ? (
                <button type="button" onClick={() => openCreate('onedrive')} className={addButtonClass}>
                    Add OneDrive
                </button>
            ) : null}
            <button type="button" onClick={openAddR2} className={addButtonClass}>
                Add Cloudflare R2
            </button>
        </div>
    );

    return (
        <div className="space-y-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 className="text-lg font-semibold text-slate-900">Storage accounts</h2>
                    <p className="mt-1 text-sm text-slate-600">
                        Add a provider, then connect or disconnect from its card below.
                    </p>
                </div>
                {addButtons}
            </div>

            {accountsNeedingReauth.length > 0 ? (
                <div className="space-y-3">
                    {accountsNeedingReauth.map((account) => (
                        <StorageReauthorizeBanner
                            key={account.id}
                            account={account}
                            returnUrl="/settings?tab=storage"
                        />
                    ))}
                </div>
            ) : null}

            {!hasAnyCards ? (
                <p className="text-sm text-slate-600">No storage providers added yet. Use the buttons above to add one.</p>
            ) : (
                <div className="grid gap-4">
                    {oauthApps.map((app) => {
                        const providerAccounts = accounts.filter((account) => account.provider === app.provider);

                        if (providerAccounts.length === 0) {
                            return (
                                <ProviderCard
                                    key={`app-${app.provider}`}
                                    title={app.label}
                                    subtitle={providerLabel(app.provider)}
                                    isConnected={false}
                                    onConnect={() => startOAuth(app.provider)}
                                    badges={
                                        <span className="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                            Not connected
                                        </span>
                                    }
                                >
                                    <p className="text-xs text-slate-500">
                                        App configured ({app.client_id_hint}). Connect to authorize an account.
                                    </p>
                                </ProviderCard>
                            );
                        }

                        return providerAccounts.map((account) => (
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
                                                href={`${account.reauthorize_url}?return_url=${encodeURIComponent('/settings?tab=storage')}`}
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
                                        <dt className="inline">App: </dt>
                                        <dd className="inline">{app.client_id_hint}</dd>
                                    </div>
                                </dl>
                            </ProviderCard>
                        ));
                    })}

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
                                            router.post('/storage/set-default', { account_id: account.id })
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

            {dialogOpen ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
                    <div className="w-full max-w-lg rounded-lg border border-slate-200 bg-white p-6 shadow-xl">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-slate-900">
                                Add {providerLabel(form.data.provider)}
                            </h3>
                            <button
                                type="button"
                                onClick={() => setDialogOpen(false)}
                                className="rounded-md p-1 text-slate-500 hover:bg-slate-100"
                                aria-label="Close"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form className="space-y-4" onSubmit={saveApp}>
                            <p className="text-sm text-slate-500">
                                Save OAuth app credentials first. You can connect an account from the provider card
                                after saving.
                            </p>

                            {(form.errors.client_id || form.errors.provider) && (
                                <p className="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-800">
                                    {form.errors.client_id ?? form.errors.provider}
                                </p>
                            )}

                            <input type="hidden" value={form.data.provider} readOnly />

                            <label className="block text-sm">
                                <span className="text-slate-600">Label (optional)</span>
                                <input
                                    value={form.data.label}
                                    onChange={(event) => form.setData('label', event.target.value)}
                                    className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                                    placeholder={providerLabel(form.data.provider)}
                                />
                            </label>

                            <label className="block text-sm">
                                <span className="text-slate-600">Client ID</span>
                                <input
                                    value={form.data.client_id}
                                    onChange={(event) => form.setData('client_id', event.target.value)}
                                    required
                                    className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                                    autoComplete="off"
                                />
                            </label>

                            <label className="block text-sm">
                                <span className="text-slate-600">Client secret</span>
                                <input
                                    value={form.data.client_secret}
                                    onChange={(event) => form.setData('client_secret', event.target.value)}
                                    required
                                    type="password"
                                    className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                                    autoComplete="new-password"
                                />
                            </label>

                            <label className="block text-sm">
                                <span className="text-slate-600">Redirect URI</span>
                                <input
                                    value={form.data.redirect}
                                    onChange={(event) => form.setData('redirect', event.target.value)}
                                    className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                                />
                            </label>

                            <div className="flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setDialogOpen(false)}
                                    className="rounded-md border border-slate-200 px-4 py-2 text-sm"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        !form.data.client_id.trim() ||
                                        !form.data.client_secret.trim()
                                    }
                                    className="rounded-md bg-cyan-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                                >
                                    {form.processing ? 'Adding…' : 'Add'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            ) : null}

            {r2DialogOpen ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
                    <div className="w-full max-w-lg rounded-lg border border-slate-200 bg-white p-6 shadow-xl">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-slate-900">Add Cloudflare R2</h3>
                            <button
                                type="button"
                                onClick={() => setR2DialogOpen(false)}
                                className="rounded-md p-1 text-slate-500 hover:bg-slate-100"
                                aria-label="Close"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form className="space-y-4" onSubmit={connectR2}>
                            <p className="text-sm text-slate-500">
                                Use an R2 API token with read and write access to the target bucket. Credentials are
                                stored encrypted on the connected account.
                            </p>

                            {(r2Form.errors.label ||
                                r2Form.errors.access_key_id ||
                                r2Form.errors.bucket ||
                                r2Form.errors.endpoint) && (
                                <p className="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-800">
                                    {r2Form.errors.label ??
                                        r2Form.errors.access_key_id ??
                                        r2Form.errors.bucket ??
                                        r2Form.errors.endpoint}
                                </p>
                            )}

                            <label className="block text-sm">
                                <span className="text-slate-600">Account label</span>
                                <input
                                    value={r2Form.data.label}
                                    onChange={(event) => r2Form.setData('label', event.target.value)}
                                    required
                                    className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                                    placeholder="My R2 bucket"
                                />
                            </label>

                            <label className="block text-sm">
                                <span className="text-slate-600">Access key ID</span>
                                <input
                                    value={r2Form.data.access_key_id}
                                    onChange={(event) => r2Form.setData('access_key_id', event.target.value)}
                                    required
                                    className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                                    autoComplete="off"
                                />
                            </label>

                            <label className="block text-sm">
                                <span className="text-slate-600">Secret access key</span>
                                <input
                                    value={r2Form.data.secret_access_key}
                                    onChange={(event) => r2Form.setData('secret_access_key', event.target.value)}
                                    required
                                    type="password"
                                    className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                                    autoComplete="new-password"
                                />
                            </label>

                            <label className="block text-sm">
                                <span className="text-slate-600">Bucket</span>
                                <input
                                    value={r2Form.data.bucket}
                                    onChange={(event) => r2Form.setData('bucket', event.target.value)}
                                    required
                                    className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                                />
                            </label>

                            <label className="block text-sm">
                                <span className="text-slate-600">S3 API endpoint</span>
                                <input
                                    value={r2Form.data.endpoint}
                                    onChange={(event) => r2Form.setData('endpoint', event.target.value)}
                                    required
                                    className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                                    placeholder="https://&lt;account_id&gt;.r2.cloudflarestorage.com"
                                />
                            </label>

                            <label className="block text-sm">
                                <span className="text-slate-600">Region (optional)</span>
                                <input
                                    value={r2Form.data.region}
                                    onChange={(event) => r2Form.setData('region', event.target.value)}
                                    className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                                    placeholder="auto"
                                />
                            </label>

                            <label className="block text-sm">
                                <span className="text-slate-600">Path prefix (optional)</span>
                                <input
                                    value={r2Form.data.prefix}
                                    onChange={(event) => r2Form.setData('prefix', event.target.value)}
                                    className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                                    placeholder="xflickr"
                                />
                            </label>

                            <div className="flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setR2DialogOpen(false)}
                                    className="rounded-md border border-slate-200 px-4 py-2 text-sm"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={
                                        r2Form.processing ||
                                        !r2Form.data.label.trim() ||
                                        !r2Form.data.access_key_id.trim() ||
                                        !r2Form.data.secret_access_key.trim() ||
                                        !r2Form.data.bucket.trim() ||
                                        !r2Form.data.endpoint.trim()
                                    }
                                    className="rounded-md bg-cyan-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                                >
                                    {r2Form.processing ? 'Adding…' : 'Add'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
