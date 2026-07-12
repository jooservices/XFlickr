import { router, useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

import Button from '@/Components/Button';
import {
    FlickrAccountProfileCard,
    FlickrAppProfileCard,
    type FlickrAppSummary,
} from '@/Components/Settings/FlickrProfileCard';
import { useFlickrCrawlSummaries } from '@/hooks/useFlickrCrawlSummaries';
import type { FlickrAccountSummary } from '@/types';

interface FlickrCredentialsPanelProps {
    accounts: FlickrAccountSummary[];
    apps: FlickrAppSummary[];
    default_callback_url: string;
}

function accountsForProfile(accounts: FlickrAccountSummary[], profile: string): FlickrAccountSummary[] {
    return accounts.filter((account) => (account.app_profile ?? 'main') === profile);
}

export default function FlickrCredentialsPanel({
    accounts,
    apps,
    default_callback_url,
}: FlickrCredentialsPanelProps) {
    const [appDialogOpen, setAppDialogOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<FlickrAppSummary | null>(null);
    const [deleting, setDeleting] = useState(false);
    const summaries = useFlickrCrawlSummaries(accounts);

    const orphanAccounts = useMemo(() => {
        const profiles = new Set(apps.map((app) => app.profile));

        return accounts.filter((account) => !profiles.has(account.app_profile ?? 'main'));
    }, [accounts, apps]);

    const hasAnyCards = apps.length > 0 || orphanAccounts.length > 0;

    const form = useForm({
        profile: 'main',
        label: '',
        api_key: '',
        api_secret: '',
        callback_url: default_callback_url,
    });

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

    const startOAuth = (profile: string) => {
        window.location.href = `/flickr/oauth?app_profile=${encodeURIComponent(profile)}`;
    };

    const reconnectAccount = (account: FlickrAccountSummary) => {
        if (apps.length === 0) {
            resetForm();
            setAppDialogOpen(true);
            return;
        }

        startOAuth(account.app_profile ?? apps[0]?.profile ?? 'main');
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

    const renderAccountCard = (account: FlickrAccountSummary, app: FlickrAppSummary | null) => (
        <FlickrAccountProfileCard
            key={account.public_id}
            account={account}
            app={app}
            rateLimit={summaries[account.nsid]?.rate_limit ?? null}
            onConnect={() => reconnectAccount(account)}
            onConnectAnother={app ? () => startOAuth(app.profile) : undefined}
        />
    );

    return (
        <div className="space-y-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 className="text-lg font-semibold text-slate-900">Flickr connections</h2>
                    <p className="mt-1 text-sm text-slate-600">
                        Add a Flickr app profile, then connect one or more accounts from its card.
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

            {!hasAnyCards ? (
                <p className="text-sm text-slate-600">No Flickr apps configured yet. Add one to continue.</p>
            ) : (
                <div className="grid gap-4">
                    {apps.map((app) => {
                        const profileAccounts = accountsForProfile(accounts, app.profile);

                        if (profileAccounts.length === 0) {
                            return (
                                <FlickrAppProfileCard
                                    key={`app-${app.profile}`}
                                    app={app}
                                    defaultCallbackUrl={default_callback_url}
                                    onDelete={() => setDeleteTarget(app)}
                                    onConnect={() => startOAuth(app.profile)}
                                />
                            );
                        }

                        return profileAccounts.map((account) => renderAccountCard(account, app));
                    })}

                    {orphanAccounts.map((account) => renderAccountCard(account, null))}
                </div>
            )}

            {appDialogOpen ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
                    <div className="w-full max-w-lg rounded-lg border border-slate-200 bg-white p-6 shadow-xl">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-slate-900">Flickr app credentials</h3>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => setAppDialogOpen(false)}
                                aria-label="Close"
                            >
                                <X className="h-5 w-5" />
                            </Button>
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
                                <Button type="button" variant="secondary" onClick={() => setAppDialogOpen(false)}>
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant="primary"
                                    disabled={form.processing || !form.data.api_key.trim() || !form.data.api_secret.trim()}
                                >
                                    {form.processing ? 'Saving…' : 'Save app'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            ) : null}

            {deleteTarget ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
                    <div className="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-xl">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-slate-900">Delete Flickr app?</h3>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => setDeleteTarget(null)}
                                aria-label="Close"
                                disabled={deleting}
                            >
                                <X className="h-5 w-5" />
                            </Button>
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
