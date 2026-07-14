import { Clock } from 'lucide-react';

export default function PhotoQueuedBadge() {
    return (
        <span
            className="inline-flex rounded-full bg-black/50 p-1 text-amber-300"
            aria-label="Download queued"
            title="Download queued"
        >
            <Clock className="size-4" />
        </span>
    );
}
