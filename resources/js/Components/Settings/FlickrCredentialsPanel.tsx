import { router, useForm } from '@inertiajs/react';
import { Plus, Trash2, X } from 'lucide-react';
import { FormEvent, useCallback, useEffect, useState } from 'react';

import Button from '@/Components/Button';
import ProviderCard from '@/Components/ProviderCard';
import { apiGet } from '@/lib/apiClient';
import { flickrApiAccountPath } from '@/lib/flickrAccount';
import type { CrawlSummary, FlickrAccountSummary } from '@/types';

interface FlickrAppSummary {
    profile: string;
    label: string | null;
    api_key_hint: string;
    callback_url: string | null;
    accounts_count: number;
}

interface FlickrCredentialsPanelProps {
    accounts: FlickrAccountSummary[];
    apps: FlickrAppSummary[];
    default_callback_url: string;
}

export default function FlickrCredentialsPanel({
    accounts,
    apps,
    default_callback_url,
}: FlickrCredentialsPanelProps) {
    const [appDialogOpen, setAppDialogOpen] = useState(false);
    const [profilePickerOpen, setProfilePickerOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<FlickrAppSummary | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [selectedProfile, setSelectedProfile] = useState(apps[0]?.profile ?? 'main');
    const [summaries, setSummaries] = useState<Record<string, CrawlSummary>>({});

    const form = useForm({
        profile: 'main',
        label: '',
        api_key: '',
        api_secret: '',
        callback_url: default_callback_url,
    });

    useEffect(() => {
        if (apps.length === 0) {
            return;
        }

        if (!apps.some((app) => app.profile === selectedProfile)) {
            setSelectedProfile(apps[0]?.profile ?? 'main');
        }
    }, [apps, selectedProfile]);

    const loadSummaries = useCallback(async () => {
        const connected = accounts.filter((account) => account.is_connected);
        const entries = await Promise.all(
            connected.map(async (account) => {
                try {
                    const summary = await apiGet<CrawlSummary>(
                        flickrApiAccountPath(account.public_id, '/crawl/summary'),
                    );

                    return [account.nsid, summary] as const;
                } catch {
                    return [account.nsid, null] as const;
                }
            }),
        );

        setSummaries(
            Object.fromEntries(entries.filter(([, summary]) => summary !== null).map(([id, summary]) => [id, summary!])),
        );
    }, [accounts]);

    useEffect(() => {
        void loadSummaries();
        const interval = setInterval(() => void loadSummaries(), 10000);

        return () => clearInterval(interval);
    }, [loadSummaries]);

    const resetForm = () => {
        form.setData({
            profile: 'main',
            label: '',
            api_key: '',
            api_secret: '',
            callback_url: default_callback_url,
        });
        form.clearErrors();
    };

    const saveApp = (event: FormEvent) => {
        event.preventDefault();
        form.post('/settings/flickr-app', {
            preserveScroll: true,
            onSuccess: () => {
                resetForm();
                setAppDialogOpen(false);
            },
        });
    };

    const beginConnect = (account: FlickrAccountSummary) => {
        if (apps.length === 0) {
            resetForm();
            setAppDialogOpen(true);
            return;
        }

        const profile = account.app_profile ?? apps[0]?.profile ?? 'main';

        if (apps.length === 1) {
            window.location.href = `/flickr/oauth?app_profile=${encodeURIComponent(apps[0]!.profile)}`;
            return;
        }

        setSelectedProfile(profile);
        setProfilePickerOpen(true);
    };

    const confirmConnect = () => {
        window.location.href = `/flickr/oauth?app_profile=${encodeURIComponent(selectedProfile)}`;
    };

    const startOAuth = (profile: string) => {
        window.location.href = `/flickr/oauth?app_profile=${encodeURIComponent(profile)}`;
    };

    const confirmDelete = () => {
        if (!deleteTarget || deleteTarget.accounts_count > 0) {
            return;
        }

        setDeleting(true);
        router.delete(`/settings/flickr-app/${encodeURIComponent(deleteTarget.profile)}`, {
            preserveScroll: true,
            onFinish: () => setDeleting(false),
            onSuccess: () => setDeleteTarget(null),
        });
    };

    const formErrorEntries = Object.entries(form.errors).filter(([, message]) => message !== '');

    return (
        <div className="space-y-8">
            <div className="space-y-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">Flickr apps</h2>
                        <p className="mt-1 text-sm text-slate-600">
                            API key and secret from your Flickr developer app. Required before connecting an account.
                        </p>
                    </div>
                    <Button
                        variant="secondary"
                        onClick={() => {
                            resetForm();
                            setAppDialogOpen(true);
                        }}
                        className="shadow-sm"
                    >
                        <Plus className="h-4 w-4" />
                        Add Flickr app
                    </Button>
                </div>

                {apps.length === 0 ? (
                    <p className="text-sm text-slate-600">No Flickr apps configured yet. Add one to continue.</p>
                ) : (
                    <div className="grid gap-4">
                        {apps.map((app) => (
                            <ProviderCard
                                key={app.profile}
                                title={app.label ?? `Profile ${app.profile}`}
                                subtitle={`Profile: ${app.profile}`}
                                isConnected={false}
                                onConnect={() => startOAuth(app.profile)}
                                extraHeaderActions={
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => setDeleteTarget(app)}
                                        title="Delete app profile"
                                    >
                                        <Trash2 className="size-3.5" />
                                        Delete
                                    </Button>
                                }
                                badges={
                                    <span className="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                        {app.api_key_hint}
                                    </span>
                                }
                                quotaFallback={
                                    <p className="text-xs text-slate-500">
                                        Callback: {app.callback_url ?? default_callback_url}
                                    </p>
                                }
                            >
                                <p className="text-xs text-slate-500">
                                    {app.accounts_count === 0
                                        ? 'App saved. Connect a Flickr account to use it.'
                                        : `${app.accounts_count} connected account${app.accounts_count === 1 ? '' : 's'}.`}
                                </p>
                            </ProviderCard>
                        ))}
                    </div>
                )}
            </div>

            <div className="space-y-6">
                <div>
                    <h2 className="text-lg font-semibold text-slate-900">Flickr accounts</h2>
                    <p className="mt-1 text-sm text-slate-600">OAuth-connected Flickr users tied to an app profile above.</p>
                </div>

                {accounts.length === 0 ? (
                    <p className="text-sm text-slate-600">
                        {apps.length === 0
                            ? 'No Flickr accounts yet. Add a Flickr app first, then connect an account.'
                            : 'No Flickr accounts connected yet. Use Connect on an app card above.'}
                    </p>
                ) : (
                <div className="grid gap-4">
                    {accounts.map((account) => (
                        <ProviderCard
                            key={account.nsid}
                            title={account.username ?? account.nsid}
                            subtitle={account.fullname ?? undefined}
                            isConnected={account.is_connected}
                            onConnect={() => beginConnect(account)}
                            onDisconnect={
                                account.is_connected
                                    ? () => router.post('/flickr/disconnect', { connection_key: account.nsid })
                                    : undefined
                            }
                            rateLimit={summaries[account.nsid]?.rate_limit ?? null}
                            badges={
                                account.is_active ? (
                                    <span className="rounded bg-cyan-50 px-2 py-0.5 text-xs font-medium text-cyan-800">
                                        Active
                                    </span>
                                ) : account.is_connected ? (
                                    <Button
                                        variant="link"
                                        size="xs"
                                        onClick={() =>
                                            router.post('/flickr/activate', { connection_key: account.nsid })
                                        }
                                        className="px-0"
                                    >
                                        Set active
                                    </Button>
                                ) : (
                                    <span className="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                        Disconnected
                                    </span>
                                )
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
                                <div>
                                    <dt className="inline">Connected: </dt>
                                    <dd className="inline">{account.connected_at ?? '—'}</dd>
                                </div>
                            </dl>
                        </ProviderCard>
                    ))}
                </div>
                )}
            </div>

            {appDialogOpen ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
                    <div className="w-full max-w-lg rounded-lg border border-slate-200 bg-white p-6 shadow-xl">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-slate-900">Flickr app credentials</h3>
                            <button
                                type="button"
                                onClick={() => setAppDialogOpen(false)}
                                className="rounded-md p-1 text-slate-500 hover:bg-slate-100"
                                aria-label="Close"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form className="space-y-4" onSubmit={saveApp}>
                            <p className="text-sm text-slate-500">
                                Credentials are stored in MongoDB via laravel-config (`xflickr_app.{'{profile}'}`).
                            </p>

                            {formErrorEntries.length > 0 ? (
                                <div className="space-y-1 rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-800">
                                    {formErrorEntries.map(([field, message]) => (
                                        <p key={field}>{message}</p>
                                    ))}
                                </div>
                            ) : null}

                            <label className="block text-sm">
                                <span className="text-slate-600">Profile slug</span>
                                <input
                                    value={form.data.profile}
                                    onChange={(event) => form.setData('profile', event.target.value)}
                                    required
                                    pattern="[a-zA-Z0-9_-]+"
                                    className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                                    placeholder="main"
                                />
                            </label>

                            <label className="block text-sm">
                                <span className="text-slate-600">Label (optional)</span>
                                <input
                                    value={form.data.label}
                                    onChange={(event) => form.setData('label', event.target.value)}
                                    className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                                    placeholder="Personal Flickr app"
                                />
                            </label>

                            <label className="block text-sm">
                                <span className="text-slate-600">API key</span>
                                <input
                                    value={form.data.api_key}
                                    onChange={(event) => form.setData('api_key', event.target.value)}
                                    required
                                    className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                                    autoComplete="off"
                                />
                            </label>

                            <label className="block text-sm">
                                <span className="text-slate-600">API secret</span>
                                <input
                                    value={form.data.api_secret}
                                    onChange={(event) => form.setData('api_secret', event.target.value)}
                                    required
                                    type="password"
                                    className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                                    autoComplete="new-password"
                                />
                            </label>

                            <label className="block text-sm">
                                <span className="text-slate-600">Callback URL</span>
                                <input
                                    value={form.data.callback_url}
                                    onChange={(event) => form.setData('callback_url', event.target.value)}
                                    className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                                />
                            </label>

                            <div className="flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setAppDialogOpen(false)}
                                    className="rounded-md border border-slate-200 px-4 py-2 text-sm"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={form.processing || !form.data.api_key.trim() || !form.data.api_secret.trim()}
                                    className="rounded-md bg-cyan-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                                >
                                    {form.processing ? 'Saving…' : 'Save app'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            ) : null}

            {profilePickerOpen ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
                    <div className="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-xl">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-slate-900">Choose Flickr app</h3>
                            <button
                                type="button"
                                onClick={() => setProfilePickerOpen(false)}
                                className="rounded-md p-1 text-slate-500 hover:bg-slate-100"
                                aria-label="Close"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <label className="block text-sm">
                            <span className="text-slate-600">App profile</span>
                            <select
                                value={selectedProfile}
                                onChange={(event) => setSelectedProfile(event.target.value)}
                                className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                            >
                                {apps.map((app) => (
                                    <option key={app.profile} value={app.profile}>
                                        {(app.label || `Profile ${app.profile}`) + ` (${app.api_key_hint})`}
                                    </option>
                                ))}
                            </select>
                        </label>

                        <div className="mt-4 flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => setProfilePickerOpen(false)}
                                className="rounded-md border border-slate-200 px-4 py-2 text-sm"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={confirmConnect}
                                className="rounded-md bg-cyan-700 px-4 py-2 text-sm font-medium text-white"
                            >
                                Connect
                            </button>
                        </div>
                    </div>
                </div>
            ) : null}

            {deleteTarget ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
                    <div className="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-xl">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-slate-900">Delete Flickr app?</h3>
                            <button
                                type="button"
                                onClick={() => setDeleteTarget(null)}
                                className="rounded-md p-1 text-slate-500 hover:bg-slate-100"
                                aria-label="Close"
                                disabled={deleting}
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <p className="text-sm text-slate-600">
                            This removes API credentials for{' '}
                            <span className="font-medium text-slate-900">
                                {deleteTarget.label ?? `Profile ${deleteTarget.profile}`}
                            </span>{' '}
                            (<span className="font-mono text-xs">{deleteTarget.profile}</span>) from MongoDB. This
                            cannot be undone.
                        </p>

                        {deleteTarget.accounts_count > 0 ? (
                            <p className="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                {deleteTarget.accounts_count} Flickr account
                                {deleteTarget.accounts_count === 1 ? ' is' : 's are'} still linked to this profile.
                                Disconnect them before deleting the app.
                            </p>
                        ) : null}

                        <div className="mt-6 flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => setDeleteTarget(null)}
                                disabled={deleting}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={confirmDelete}
                                disabled={deleting || deleteTarget.accounts_count > 0}
                            >
                                {deleting ? 'Deleting…' : 'Delete app'}
                            </Button>
                        </div>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
