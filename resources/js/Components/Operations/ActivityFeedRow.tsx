import type { ActivityFeedEntry } from '@/lib/activityFeed';
import { cn } from '@/lib/cn';
import { formatSyncedAt } from '@/lib/format';

function typeBadgeClass(type: string): string {
    switch (type) {
        case 'domain':
            return 'bg-cyan-100 text-cyan-800';
        case 'audit':
            return 'bg-slate-100 text-slate-700';
        case 'system':
            return 'bg-emerald-100 text-emerald-800';
        case 'security':
            return 'bg-rose-100 text-rose-800';
        default:
            return 'bg-slate-100 text-slate-600';
    }
}

function accentClass(level: string | null): string {
    if (level === 'error' || level === 'critical' || level === 'alert' || level === 'emergency') {
        return 'border-l-rose-500';
    }
    if (level === 'warning') {
        return 'border-l-amber-500';
    }

    return 'border-l-slate-200';
}

function propertySummary(entry: ActivityFeedEntry): string {
    if (entry.message) {
        return entry.message;
    }

    const parts: string[] = [];
    const props = entry.properties;

    if (typeof props.run_id === 'number' || typeof props.run_id === 'string') {
        parts.push(`Run #${props.run_id}`);
    }
    if (typeof props.batch_id === 'number' || typeof props.batch_id === 'string') {
        parts.push(`Batch #${props.batch_id}`);
    }
    if (typeof props.target_id === 'number' || typeof props.target_id === 'string') {
        parts.push(`Target #${props.target_id}`);
    }
    if (typeof props.connection_key_fp === 'string' && props.connection_key_fp !== '') {
        parts.push(`fp:${props.connection_key_fp}`);
    }
    if (typeof props.reason === 'string' && props.reason !== '') {
        parts.push(props.reason);
    }
    if (typeof props.crawl_type === 'string' && props.crawl_type !== '') {
        parts.push(`crawl_type=${props.crawl_type}`);
    }
    if (typeof props.count === 'number') {
        parts.push(`count=${props.count}`);
    }
    if (typeof props.queued_count === 'number') {
        parts.push(`queued=${props.queued_count}`);
    }
    if (typeof props.source_id === 'string' && props.source_id !== '') {
        parts.push(`photo=${props.source_id}`);
    }

    return parts.join(' · ') || '—';
}

function actorLabel(entry: ActivityFeedEntry): string {
    if (entry.actor === null) {
        return 'system';
    }

    if (entry.actor.type === 'system' || entry.actor.id === null) {
        return entry.actor.type;
    }

    return `${entry.actor.type}#${entry.actor.id}`;
}

interface ActivityFeedRowProps {
    entry: ActivityFeedEntry;
    selected: boolean;
    onSelect: (entry: ActivityFeedEntry) => void;
}

export default function ActivityFeedRow({ entry, selected, onSelect }: ActivityFeedRowProps) {
    const level = entry.level;
    const showLevelMark =
        level === 'warning' || level === 'error' || level === 'critical' || level === 'alert' || level === 'emergency';

    return (
        <button
            type="button"
            onClick={() => onSelect(entry)}
            className={cn(
                'w-full border-l-4 px-4 py-3 text-left transition-colors',
                accentClass(entry.level),
                selected ? 'bg-cyan-50/70' : 'bg-white hover:bg-slate-50',
            )}
        >
            <div className="flex items-start gap-3">
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-medium text-slate-900">{entry.action}</span>
                        <span className={cn('inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize', typeBadgeClass(entry.type))}>
                            {entry.type}
                        </span>
                        {showLevelMark ? (
                            <span
                                className={cn(
                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                    level === 'warning' ? 'bg-amber-100 text-amber-900' : 'bg-rose-100 text-rose-800',
                                )}
                            >
                                {level}
                            </span>
                        ) : null}
                    </div>
                    <p className="mt-1 truncate text-sm text-slate-600">{propertySummary(entry)}</p>
                    <p className="mt-1 text-xs text-slate-500">
                        {entry.occurred_at ? formatSyncedAt(entry.occurred_at) : '—'} · {actorLabel(entry)}
                    </p>
                </div>
            </div>
        </button>
    );
}
