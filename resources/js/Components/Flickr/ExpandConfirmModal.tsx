import SpiderImpactSummary from '@/Components/Operations/SpiderImpactSummary';
import Button from '@/Components/ui/Button';
import LoadingIndicator from '@/Components/ui/LoadingIndicator';
import Modal from '@/Components/ui/Modal';
import type { ExpandPreviewPayload } from '@/types';

export type ExpandMode = 'spider' | 'full_pass';

interface ExpandConfirmModalProps {
    mode: ExpandMode;
    preview: ExpandPreviewPayload | null;
    loading: boolean;
    submitting: boolean;
    onClose: () => void;
    onConfirm: () => void;
}

function accountLabel(preview: ExpandPreviewPayload | null): string {
    if (!preview) {
        return '—';
    }

    return preview.account.username ?? preview.account.fullname ?? preview.account.nsid;
}

export default function ExpandConfirmModal({
    mode,
    preview,
    loading,
    submitting,
    onClose,
    onConfirm,
}: ExpandConfirmModalProps) {
    const isSpider = mode === 'spider';
    const title = isSpider ? 'Auto-expand (Spider)' : 'Full contact pass';
    const spiderEnabled = preview?.spider.enabled ?? false;
    const spiderActive = preview?.spider.active ?? false;
    const fullPassActive = preview?.full_pass.active ?? false;
    const conflict = isSpider ? fullPassActive : spiderActive;
    const disabled =
        submitting ||
        loading ||
        preview === null ||
        conflict ||
        (isSpider && !spiderEnabled);

    return (
        <Modal open onClose={onClose} closeDisabled={submitting} titleId="expand-confirm-title" size="md">
            <Modal.Header title={title} />
            <Modal.Body className="space-y-4 text-sm text-slate-600">
                {loading ? (
                    <LoadingIndicator size="sm" label="Loading preview…" />
                ) : preview === null ? (
                    <p className="text-rose-700">Could not load expand preview.</p>
                ) : (
                    <>
                        <div className="rounded-md border border-slate-100 bg-slate-50 px-3 py-2">
                            <p className="text-xs font-medium text-slate-500">Account</p>
                            <p className="font-medium text-slate-900">{accountLabel(preview)}</p>
                            <p className="font-mono text-xs text-slate-500">{preview.account.nsid}</p>
                            <p className="mt-1 text-xs text-slate-500">
                                Saved contacts: {preview.saved_contacts_count.toLocaleString()}
                            </p>
                        </div>

                        {isSpider ? (
                            <>
                                <dl className="grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <dt className="text-xs text-slate-500">Spider enabled</dt>
                                        <dd className="font-medium text-slate-900">{spiderEnabled ? 'Yes' : 'No'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-slate-500">Max depth</dt>
                                        <dd className="font-medium text-slate-900">{preview.spider.max_depth}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-slate-500">Batch per tick</dt>
                                        <dd className="font-medium text-slate-900">
                                            {preview.spider.max_new_contacts_per_run}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-slate-500">Total cap</dt>
                                        <dd className="font-medium text-slate-900">{preview.spider.max_contacts_total}</dd>
                                    </div>
                                </dl>
                                {!spiderEnabled ? (
                                    <p className="rounded-md bg-amber-50 px-3 py-2 text-amber-900">
                                        Enable spider mode from the header <span className="font-medium">Spider</span>{' '}
                                        control (or Settings → General) before starting.
                                    </p>
                                ) : null}
                                {spiderActive ? (
                                    <p className="rounded-md bg-amber-50 px-3 py-2 text-amber-900">
                                        A spider run is already active for this account.
                                    </p>
                                ) : null}
                                {preview.spider.impact ? (
                                    <SpiderImpactSummary impact={preview.spider.impact} context="account" />
                                ) : null}
                                <p className="text-xs text-slate-500">
                                    Refreshes your contact list, then expands automatically via the scheduler. Primarily
                                    crawls photos and discovers new public contacts up to the configured depth.
                                </p>
                            </>
                        ) : (
                            <>
                                <dl className="grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <dt className="text-xs text-slate-500">Max depth</dt>
                                        <dd className="font-medium text-slate-900">{preview.full_pass.max_depth}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-slate-500">Batch size</dt>
                                        <dd className="font-medium text-slate-900">
                                            {preview.full_pass.max_contacts_per_batch}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-slate-500">Total cap</dt>
                                        <dd className="font-medium text-slate-900">
                                            {preview.full_pass.max_contacts_total}
                                        </dd>
                                    </div>
                                </dl>
                                {fullPassActive ? (
                                    <p className="rounded-md bg-amber-50 px-3 py-2 text-amber-900">
                                        A full contact pass is already active for this account.
                                    </p>
                                ) : null}
                                <p className="text-xs text-slate-500">
                                    Refreshes your contact list, then queues photos, favorites, photosets, and galleries
                                    for each contact up to the configured depth. Also discovers public contacts of each
                                    processed contact, then stops when the pass completes.
                                </p>
                            </>
                        )}

                        {conflict ? (
                            <p className="rounded-md bg-amber-50 px-3 py-2 text-amber-900">
                                Stop the other expand run before starting this one.
                            </p>
                        ) : null}
                    </>
                )}
            </Modal.Body>
            <Modal.Footer>
                <Button type="button" variant="secondary" onClick={onClose} disabled={submitting}>
                    Cancel
                </Button>
                <Button type="button" variant="primary" onClick={onConfirm} disabled={disabled}>
                    {submitting ? 'Starting…' : 'Start'}
                </Button>
            </Modal.Footer>
        </Modal>
    );
}
