import { CheckCircle2 } from 'lucide-react';

interface DownloadedBadgeProps {
    href: string;
}

export default function DownloadedBadge({ href }: DownloadedBadgeProps) {
    return (
        <a
            href={href}
            target="_blank"
            rel="noreferrer"
            aria-label="Open downloaded photo"
            className="inline-flex rounded-full bg-black/50 p-1 text-emerald-400 hover:bg-black/70"
            onClick={(event) => event.stopPropagation()}
        >
            <CheckCircle2 className="size-4" />
        </a>
    );
}
