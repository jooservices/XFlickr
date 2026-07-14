import { router } from '@inertiajs/react';
import { Bug } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import Button from '@/Components/Button';
import Modal from '@/Components/Modal';
import SpiderImpactSummary from '@/Components/SpiderImpactSummary';
import { estimateSpiderImpact } from '@/lib/spiderImpact';
import type { SpiderSharedConfig } from '@/types';

type SpiderModeModalProps = {
    open: boolean;
    spider: SpiderSharedConfig;
    onClose: () => void;
};

export default function SpiderModeModal({ open, spider, onClose }: SpiderModeModalProps) {
    const [enabled, setEnabled] = useState(spider.enabled);
    const [maxDepth, setMaxDepth] = useState(String(spider.max_depth));
    const [maxNewContactsPerRun, setMaxNewContactsPerRun] = useState(String(spider.max_new_contacts_per_run));
    const [maxContactsTotal, setMaxContactsTotal] = useState(String(spider.max_contacts_total));
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        setEnabled(spider.enabled);
        setMaxDepth(String(spider.max_depth));
        setMaxNewContactsPerRun(String(spider.max_new_contacts_per_run));
        setMaxContactsTotal(String(spider.max_contacts_total));
    }, [open, spider]);

    const impact = useMemo(
        () =>
            estimateSpiderImpact(
                Number(maxDepth) || 0,
                Number(maxNewContactsPerRun) || 1,
                Number(maxContactsTotal) || 1,
            ),
        [maxDepth, maxNewContactsPerRun, maxContactsTotal],
    );

    function save(): void {
        if (busy) {
            return;
        }

        setBusy(true);
        router.post(
            '/settings/spider',
            {
                enabled: enabled ? 1 : 0,
                max_depth: Number(maxDepth),
                max_new_contacts_per_run: Number(maxNewContactsPerRun),
                max_contacts_total: Number(maxContactsTotal),
            },
            {
                preserveScroll: true,
                onFinish: () => setBusy(false),
                onSuccess: () => onClose(),
            },
        );
    }

    return (
        <Modal open={open} onClose={onClose} closeDisabled={busy} titleId="spider-mode-title" size="md">
            <Modal.Header title="Spider mode" />
            <Modal.Body className="space-y-4 text-sm text-slate-600">
                <p>
                    Opt-in breadth-first contact expansion. Enabling this only allows spider runs — start or stop a run
                    from a Flickr account&apos;s Expand actions.
                </p>

                <label className="flex items-center gap-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                    <input
                        type="checkbox"
                        className="h-4 w-4 rounded border-slate-300 text-cyan-700 focus:ring-cyan-600"
                        checked={enabled}
                        onChange={(event) => setEnabled(event.target.checked)}
                        disabled={busy}
                    />
                    <span className="font-medium text-slate-900">Enable spider mode</span>
                </label>

                <div className="grid gap-3 sm:grid-cols-3">
                    <label className="block space-y-1">
                        <span className="text-xs font-medium text-slate-500">Max depth</span>
                        <input
                            type="number"
                            min={0}
                            max={10}
                            className="w-full rounded-md border border-slate-200 px-3 py-2 text-slate-900"
                            value={maxDepth}
                            onChange={(event) => setMaxDepth(event.target.value)}
                            disabled={busy}
                        />
                    </label>
                    <label className="block space-y-1">
                        <span className="text-xs font-medium text-slate-500">Batch per tick</span>
                        <input
                            type="number"
                            min={1}
                            max={500}
                            className="w-full rounded-md border border-slate-200 px-3 py-2 text-slate-900"
                            value={maxNewContactsPerRun}
                            onChange={(event) => setMaxNewContactsPerRun(event.target.value)}
                            disabled={busy}
                        />
                    </label>
                    <label className="block space-y-1">
                        <span className="text-xs font-medium text-slate-500">Total cap</span>
                        <input
                            type="number"
                            min={1}
                            max={10000}
                            className="w-full rounded-md border border-slate-200 px-3 py-2 text-slate-900"
                            value={maxContactsTotal}
                            onChange={(event) => setMaxContactsTotal(event.target.value)}
                            disabled={busy}
                        />
                    </label>
                </div>

                <SpiderImpactSummary impact={impact} context="caps" />

                <p className="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-500">
                    Enabling spider also clears global crawl pause so jobs can dispatch.
                </p>
            </Modal.Body>
            <Modal.Footer>
                <Button type="button" variant="secondary" onClick={onClose} disabled={busy}>
                    Cancel
                </Button>
                <Button type="button" variant="primary" onClick={save} disabled={busy}>
                    {busy ? 'Saving…' : 'Save'}
                </Button>
            </Modal.Footer>
        </Modal>
    );
}

type SpiderModeButtonProps = {
    spider: SpiderSharedConfig;
};

export function SpiderModeButton({ spider }: SpiderModeButtonProps) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                type="button"
                size="sm"
                variant={spider.enabled ? 'connect' : 'secondary'}
                onClick={() => setOpen(true)}
                aria-pressed={spider.enabled}
                aria-label={spider.enabled ? 'Spider mode on — open settings' : 'Spider mode off — open settings'}
                title={spider.enabled ? 'Spider mode on' : 'Spider mode off'}
            >
                <Bug className="h-4 w-4" />
                <span className="hidden sm:inline">{spider.enabled ? 'Spider on' : 'Spider'}</span>
            </Button>

            <SpiderModeModal open={open} spider={spider} onClose={() => setOpen(false)} />
        </>
    );
}
