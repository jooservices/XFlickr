interface StatusBadgeProps {
    status: string;
}

export default function StatusBadge({ status }: StatusBadgeProps) {
    const tone =
        status === 'running'
            ? 'bg-blue-100 text-blue-800'
            : status === 'failed'
              ? 'bg-red-100 text-red-800'
              : 'bg-slate-100 text-slate-700';

    return (
        <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize ${tone}`}>
            {status}
        </span>
    );
}
