import { router, useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

import Button from '@/Components/Button';
import {
    FlickrAppProfileCard,
    type FlickrAppSummary,
} from '@/Components/Flickr/FlickrAppProfileCard';
import Modal from '@/Components/Modal';

interface FlickrAppsPanelProps {
    apps: FlickrAppSummary[];
    defaultCallbackUrl: string;
    openAddRequest?: number;
    showHeading?: boolean;
}

export default function FlickrAppsPanel({
    apps,
    defaultCallbackUrl,
    openAddRequest = 0,
    showHeading = false,
}: FlickrAppsPanelProps) {
    const [appDialogOpen, setAppDialogOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<FlickrAppSummary | null>(null);
    const [deleting, setDeleting] = useState(false);

    const form = useForm({
        profile: 'main',
        label: '',
        api_key: '',
        api_secret: '',
        callback_url: defaultCallbackUrl,
    });

    const resetForm = () => {
        form.setData({
            profile: 'main',
            label: '',
            api_key: '',
            api_secret: '',
            callback_url: defaultCallbackUrl,
        });
        form.clearErrors();
    };

    const openAddDialog = () => {
        resetForm();
        setAppDialogOpen(true);
    };

    useEffect(() => {
        if (openAddRequest > 0) {
            openAddDialog();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- open only when parent bumps the request counter
    }, [openAddRequest]);

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
        <div className="space-y-6">
            {showHeading ? (
                <div>
                    <h2 className="text-lg font-semibold text-slate-900">API credentials</h2>
                    <p className="mt-1 text-sm text-slate-600">
                        Flickr app keys used to authorize accounts. Add credentials once, then Connect.
                    </p>
                </div>
            ) : null}

            {apps.length === 0 ? (
                <p className="text-sm text-slate-600">
                    No Flickr API credentials yet. Use <span className="font-medium">Add credentials</span> to store
                    your key and secret, then connect an account.
                </p>
            ) : (
                <div className="grid gap-4 lg:grid-cols-2">
                    {apps.map((app) => (
                        <FlickrAppProfileCard
                            key={app.profile}
                            app={app}
                            defaultCallbackUrl={defaultCallbackUrl}
                            onDelete={() => setDeleteTarget(app)}
                            onConnect={() => startOAuth(app.profile)}
                        />
                    ))}
                </div>
            )}

            <Modal
                open={appDialogOpen}
                onClose={() => setAppDialogOpen(false)}
                titleId="flickr-app-credentials-title"
                size="md"
            >
                <Modal.Header title="Flickr app credentials" />
                <form className="flex min-h-0 flex-1 flex-col" onSubmit={saveApp}>
                    <Modal.Body className="space-y-4">
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
                    </Modal.Body>
                    <Modal.Footer>
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
                    </Modal.Footer>
                </form>
            </Modal>

            <Modal
                open={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                closeDisabled={deleting}
                titleId="flickr-app-delete-title"
                size="sm"
            >
                <Modal.Header title="Delete Flickr app?" />
                <Modal.Body className="space-y-3 text-sm text-slate-600">
                    <p>
                        This removes API credentials for{' '}
                        <span className="font-medium text-slate-900">
                            {deleteTarget?.label ?? `Profile ${deleteTarget?.profile}`}
                        </span>{' '}
                        (<span className="font-mono text-xs">{deleteTarget?.profile}</span>) from MongoDB. This cannot
                        be undone.
                    </p>

                    {deleteTarget && deleteTarget.accounts_count > 0 ? (
                        <p className="rounded-md bg-amber-50 px-3 py-2 text-amber-900">
                            {deleteTarget.accounts_count} Flickr account
                            {deleteTarget.accounts_count === 1 ? ' is' : 's are'} still linked to this profile.
                            Disconnect them before deleting the app.
                        </p>
                    ) : null}
                </Modal.Body>
                <Modal.Footer>
                    <Button type="button" variant="secondary" onClick={() => setDeleteTarget(null)} disabled={deleting}>
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={confirmDelete}
                        disabled={deleting || (deleteTarget?.accounts_count ?? 0) > 0}
                    >
                        {deleting ? 'Deleting…' : 'Delete app'}
                    </Button>
                </Modal.Footer>
            </Modal>
        </div>
    );
}
