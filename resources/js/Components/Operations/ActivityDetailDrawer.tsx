import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';
import type { ActivityFeedEntry } from '@/lib/activityFeed';
import { formatSyncedAt } from '@/lib/format';

function JsonBlock({ label, value }: { label: string; value: Record<string, unknown> }) {
    const entries = Object.entries(value);

    if (entries.length === 0) {
        return (
            <div>
                <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</p>
                <p className="mt-1 text-sm text-slate-500">—</p>
            </div>
        );
    }

    return (
        <div>
            <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</p>
            <dl className="mt-2 space-y-1.5 rounded-md border border-slate-100 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700">
                {entries.map(([key, item]) => (
                    <div key={key} className="grid grid-cols-[minmax(0,8rem)_1fr] gap-2">
                        <dt className="truncate text-slate-500">{key}</dt>
                        <dd className="break-all text-slate-800">
                            {typeof item === 'string' || typeof item === 'number' || typeof item === 'boolean'
                                ? String(item)
                                : JSON.stringify(item)}
                        </dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}

interface ActivityDetailDrawerProps {
    entry: ActivityFeedEntry | null;
    onClose: () => void;
    onFilterCorrelation: (correlationId: string) => void;
}

export default function ActivityDetailDrawer({ entry, onClose, onFilterCorrelation }: ActivityDetailDrawerProps) {
    if (entry === null) {
        return null;
    }

    const batchId = entry.context.batch_id;
    const workflowId = entry.context.workflow_id;

    return (
        <Modal open onClose={onClose} titleId="activity-detail-title" size="lg">
            <Modal.Header title={entry.action} />
            <Modal.Body className="space-y-4 text-sm text-slate-600">
                <div className="flex flex-wrap gap-2 text-xs">
                    <span className="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-700">type={entry.type}</span>
                    {entry.level ? (
                        <span className="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-700">level={entry.level}</span>
                    ) : null}
                    <span className="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-700">
                        {entry.occurred_at ? formatSyncedAt(entry.occurred_at) : '—'}
                    </span>
                </div>

                <div className="grid gap-2 rounded-md border border-slate-100 bg-white px-3 py-2 text-xs text-slate-600 sm:grid-cols-2">
                    <p>
                        <span className="text-slate-500">correlation_id:</span>{' '}
                        <span className="font-mono text-slate-800">{entry.correlation_id ?? '—'}</span>
                    </p>
                    <p>
                        <span className="text-slate-500">batch_id:</span>{' '}
                        <span className="font-mono text-slate-800">
                            {typeof batchId === 'string' || typeof batchId === 'number' ? String(batchId) : '—'}
                        </span>
                    </p>
                    <p>
                        <span className="text-slate-500">workflow_id:</span>{' '}
                        <span className="font-mono text-slate-800">
                            {typeof workflowId === 'string' || typeof workflowId === 'number' ? String(workflowId) : '—'}
                        </span>
                    </p>
                    <p>
                        <span className="text-slate-500">subject:</span>{' '}
                        <span className="font-mono text-slate-800">
                            {entry.subject ? `${entry.subject.type}#${entry.subject.id ?? ''}` : '—'}
                        </span>
                    </p>
                </div>

                {entry.message ? <p className="text-slate-700">{entry.message}</p> : null}

                <JsonBlock label="properties" value={entry.properties} />
                <JsonBlock label="context" value={entry.context} />
                <JsonBlock label="changes" value={entry.changes} />
            </Modal.Body>
            <Modal.Footer>
                {entry.correlation_id ? (
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => {
                            onFilterCorrelation(entry.correlation_id!);
                            onClose();
                        }}
                    >
                        Filter by this correlation
                    </Button>
                ) : null}
                <Button variant="ghost" size="sm" onClick={onClose}>
                    Close
                </Button>
            </Modal.Footer>
        </Modal>
    );
}
