import type { InertiaFormProps } from '@inertiajs/react';
import { FormEvent } from 'react';

import Button from '@/Components/Button';
import Modal from '@/Components/Modal';

interface OAuthAppFormData {
    provider: string;
    label: string;
    client_id: string;
    client_secret: string;
    redirect: string;
}

interface StorageOAuthAppModalProps {
    open: boolean;
    onClose: () => void;
    form: InertiaFormProps<OAuthAppFormData>;
    providerLabel: (value: string) => string;
    onSubmit: (event: FormEvent) => void;
}

export default function StorageOAuthAppModal({
    open,
    onClose,
    form,
    providerLabel,
    onSubmit,
}: StorageOAuthAppModalProps) {
    return (
        <Modal open={open} onClose={onClose} titleId="storage-app-credentials-title" size="md">
            <Modal.Header title={`Add ${providerLabel(form.data.provider)}`} />
            <form className="flex min-h-0 flex-1 flex-col" onSubmit={onSubmit}>
                <Modal.Body className="space-y-4">
                    <p className="text-sm text-slate-500">
                        Save OAuth client credentials, then Connect from the destination card on this page.
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
                </Modal.Body>
                <Modal.Footer>
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        variant="primary"
                        disabled={form.processing || !form.data.client_id.trim() || !form.data.client_secret.trim()}
                    >
                        {form.processing ? 'Adding…' : 'Save credentials'}
                    </Button>
                </Modal.Footer>
            </form>
        </Modal>
    );
}
