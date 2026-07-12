import type { CrawlSubjectLabel } from '@/lib/crawlSubject';

export interface CrawlActionMenuHeaderProps {
    subject: CrawlSubjectLabel;
    /** Prefix before the display name (default: "Actions for") */
    prefix?: string;
}

export default function CrawlActionMenuHeader({ subject, prefix = 'Actions for' }: CrawlActionMenuHeaderProps) {
    return (
        <div className="border-b border-slate-100 bg-slate-50 px-3 py-2">
            <p className="min-w-0 truncate text-sm text-slate-600">
                <span className="text-xs font-medium text-slate-500">{prefix} </span>
                <span className="font-medium text-slate-900">{subject.title}</span>
            </p>
            <p className="truncate font-mono text-xs text-slate-500" title={subject.nsid}>
                {subject.nsid}
            </p>
        </div>
    );
}
