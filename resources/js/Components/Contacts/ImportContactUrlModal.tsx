import { router } from '@inertiajs/react';
import { useId, useState } from 'react';

import Button from '@/Components/ui/Button';
import Input from '@/Components/ui/Input';
import LoadingIndicator from '@/Components/ui/LoadingIndicator';
import Modal from '@/Components/ui/Modal';
import { apiPost } from '@/lib/apiClient';
import { flickrApiAccountPath } from '@/lib/flickrAccount';

interface ImportResult {
    nsid: string;
    username: string | null;
    realname: string | null;
    already_linked: boolean;
    crawl_started: boolean;
    redirect_path: string;
}

interface ImportContactUrlModalProps {
    accountPublicId: string;
    open: boolean;
    onClose: () => void;
}

export default function ImportContactUrlModal({
    accountPublicId,
    open,
    onClose,
}: ImportContactUrlModalProps) {
    const titleId = useId();
    const [url, setUrl] = useState('');
    const [startCrawl, setStartCrawl] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const close = () => {
        if (submitting) {
            return;
        }

        setUrl('');
        setError(null);
        setStartCrawl(true);
        onClose();
    };

    const submit = async () => {
        const trimmed = url.trim();
        if (trimmed === '') {
            setError('Paste a Flickr people or photo URL.');
            return;
        }

        setSubmitting(true);
        setError(null);

        try {
            const data = await apiPost<{ data: ImportResult }>(
                flickrApiAccountPath(accountPublicId, '/contacts'),
                {
                    source: 'url',
                    url: trimmed,
                    start_crawl: startCrawl,
                },
            );

            const redirectPath = data.data.redirect_path;
            onClose();
            router.visit(redirectPath);
        } catch (submitError) {
            setError(
                submitError instanceof Error
                    ? submitError.message
                    : 'Could not import that Flickr URL.',
            );
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <Modal open={open} onClose={close} closeDisabled={submitting} titleId={titleId} size="md">
            <Modal.Header title="Import from Flickr URL" />
            <Modal.Body className="space-y-4 text-sm text-slate-600">
                <p>
                    Paste a Flickr people, photostream, or photo page URL. We resolve the owner,
                    link them to this account, and optionally start a crawl.
                </p>
                <label className="block space-y-1">
                    <span className="text-xs font-medium text-slate-500">Flickr URL</span>
                    <Input
                        value={url}
                        onChange={(event) => setUrl(event.target.value)}
                        placeholder="https://www.flickr.com/people/…"
                        autoComplete="off"
                        disabled={submitting}
                    />
                </label>
                <label className="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input
                        type="checkbox"
                        checked={startCrawl}
                        onChange={(event) => setStartCrawl(event.target.checked)}
                        disabled={submitting}
                        className="rounded border-slate-300 text-cyan-700 focus:ring-cyan-600"
                    />
                    Start crawl after import
                </label>
                {error ? <p className="text-sm text-rose-700">{error}</p> : null}
                {submitting ? <LoadingIndicator size="sm" label="Resolving URL…" /> : null}
            </Modal.Body>
            <Modal.Footer>
                <Button type="button" variant="secondary" onClick={close} disabled={submitting}>
                    Cancel
                </Button>
                <Button type="button" variant="primary" onClick={() => void submit()} disabled={submitting}>
                    Import
                </Button>
            </Modal.Footer>
        </Modal>
    );
}
