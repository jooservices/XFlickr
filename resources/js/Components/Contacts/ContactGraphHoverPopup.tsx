import type { ContactGraphNode } from '@/types';

export default function ContactGraphHoverPopup({
    node,
    accountLabel,
    clientX,
    clientY,
}: {
    node: ContactGraphNode;
    accountLabel: string;
    clientX: number;
    clientY: number;
}) {
    return (
        <div
            className="pointer-events-none fixed z-[110] max-w-xs rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-lg"
            style={{ left: clientX + 14, top: clientY + 14 }}
        >
            <p className="truncate text-sm font-medium text-slate-900">
                {node.is_root ? accountLabel : node.label}
            </p>
            {node.username ? <p className="truncate text-xs text-cyan-700">@{node.username}</p> : null}
            <p className="truncate font-mono text-[11px] text-slate-500">{node.nsid}</p>
            <p className="mt-1 text-xs text-slate-600">
                {node.photos_count.toLocaleString()} photo{node.photos_count === 1 ? '' : 's'} · {node.child_count}{' '}
                connection{node.child_count === 1 ? '' : 's'}
                {node.starred ? ' · ★ starred' : ''}
            </p>
            {node.note_preview ? (
                <p className="mt-1 line-clamp-2 text-xs text-slate-500">{node.note_preview}</p>
            ) : null}
        </div>
    );
}
