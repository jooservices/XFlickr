import type { InertiaFormProps } from '@inertiajs/react';
import { FormEvent } from 'react';

import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';

interface R2FormData {
    label: string;
    access_key_id: string;
    secret_access_key: string;
    bucket: string;
    endpoint: string;
    region: string;
    prefix: string;
}

interface StorageR2ConnectModalProps {
    open: boolean;
    onClose: () => void;
    form: InertiaFormProps<R2FormData>;
    onSubmit: (event: FormEvent) => void;
}

export default function StorageR2ConnectModal({ open, onClose, form, onSubmit }: StorageR2ConnectModalProps) {
    return (
        <Modal open={open} onClose={onClose} titleId="storage-r2-title" size="md">
            <Modal.Header title="Add Cloudflare R2" />
            <form className="flex min-h-0 flex-1 flex-col" onSubmit={onSubmit}>
                <Modal.Body className="space-y-4">
                    <p className="text-sm text-slate-500">
                        Use an R2 API token with read and write access to the target bucket. Credentials are stored
                        encrypted on the connected account.
                    </p>

                    {(form.errors.label ||
                        form.errors.access_key_id ||
                        form.errors.bucket ||
                        form.errors.endpoint) && (
                        <p className="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-800">
                            {form.errors.label ??
                                form.errors.access_key_id ??
                                form.errors.bucket ??
                                form.errors.endpoint}
                        </p>
                    )}

                    <label className="block text-sm">
                        <span className="text-slate-600">Account label</span>
                        <input
                            value={form.data.label}
                            onChange={(event) => form.setData('label', event.target.value)}
                            required
                            className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                            placeholder="My R2 bucket"
                        />
                    </label>

                    <label className="block text-sm">
                        <span className="text-slate-600">Access key ID</span>
                        <input
                            value={form.data.access_key_id}
                            onChange={(event) => form.setData('access_key_id', event.target.value)}
                            required
                            className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                            autoComplete="off"
                        />
                    </label>

                    <label className="block text-sm">
                        <span className="text-slate-600">Secret access key</span>
                        <input
                            value={form.data.secret_access_key}
                            onChange={(event) => form.setData('secret_access_key', event.target.value)}
                            required
                            type="password"
                            className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                            autoComplete="new-password"
                        />
                    </label>

                    <label className="block text-sm">
                        <span className="text-slate-600">Bucket</span>
                        <input
                            value={form.data.bucket}
                            onChange={(event) => form.setData('bucket', event.target.value)}
                            required
                            className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                        />
                    </label>

                    <label className="block text-sm">
                        <span className="text-slate-600">S3 API endpoint</span>
                        <input
                            value={form.data.endpoint}
                            onChange={(event) => form.setData('endpoint', event.target.value)}
                            required
                            className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                            placeholder="https://&lt;account_id&gt;.r2.cloudflarestorage.com"
                        />
                    </label>

                    <label className="block text-sm">
                        <span className="text-slate-600">Region (optional)</span>
                        <input
                            value={form.data.region}
                            onChange={(event) => form.setData('region', event.target.value)}
                            className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                            placeholder="auto"
                        />
                    </label>

                    <label className="block text-sm">
                        <span className="text-slate-600">Path prefix (optional)</span>
                        <input
                            value={form.data.prefix}
                            onChange={(event) => form.setData('prefix', event.target.value)}
                            className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2"
                            placeholder="xflickr"
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
                        disabled={
                            form.processing ||
                            !form.data.label.trim() ||
                            !form.data.access_key_id.trim() ||
                            !form.data.secret_access_key.trim() ||
                            !form.data.bucket.trim() ||
                            !form.data.endpoint.trim()
                        }
                    >
                        {form.processing ? 'Adding…' : 'Add'}
                    </Button>
                </Modal.Footer>
            </form>
        </Modal>
    );
}
