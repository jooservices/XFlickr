import { AlertCircle } from 'lucide-react';

export default function PhotoFailedBadge() {
    return (
        <span
            className="inline-flex rounded-full bg-black/50 p-1 text-red-400"
            aria-label="Download failed"
            title="Download failed"
        >
            <AlertCircle className="size-4" />
        </span>
    );
}
