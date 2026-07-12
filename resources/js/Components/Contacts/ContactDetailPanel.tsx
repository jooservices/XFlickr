import { Link } from '@inertiajs/react';
import { X } from 'lucide-react';
import { type ReactNode } from 'react';

import Button from '@/Components/Button';
import ContactPhotoStrip from '@/Components/Contacts/ContactPhotoStrip';
import { flickrContactPath } from '@/lib/flickrAccount';

export interface ContactDetailSubject {
    nsid: string;
    label: string;
    username: string | null;
    photos_count: number;
    child_count: number;
    starred: boolean;
    note_preview: string | null;
    is_root?: boolean;
}

interface ContactDetailPanelProps {
    accountPublicId: string;
    accountLabel: string;
    subject: ContactDetailSubject;
    onClose: () => void;
    actions?: ReactNode;
    expandHint?: string | null;
}

export default function ContactDetailPanel({
    accountPublicId,
    accountLabel,
    subject,
    onClose,
    actions,
    expandHint = 'Expand uses Flickr public contact lists only (not private friend lists).',
}: ContactDetailPanelProps) {
    const displayName = subject.is_root ? accountLabel : subject.label;

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="shrink-0 border-b border-slate-100 p-4">
                <div className="mb-2 flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <p className="truncate font-medium text-slate-900">{displayName}</p>
                        <p className="truncate text-xs text-slate-500">
                            {subject.username ? `@${subject.username} · ` : ''}
                            {subject.nsid}
                        </p>
                    </div>
                    <Button type="button" variant="ghost" size="sm" aria-label="Close panel" onClick={onClose}>
                        <X className="h-4 w-4" />
                    </Button>
                </div>

                <p className="text-sm text-slate-600">
                    {subject.photos_count.toLocaleString()} photo{subject.photos_count === 1 ? '' : 's'} ·{' '}
                    {subject.child_count.toLocaleString()} connection{subject.child_count === 1 ? '' : 's'}
                    {subject.starred ? ' · ★ starred' : ''}
                </p>

                {!subject.is_root ? (
                    <Link
                        href={flickrContactPath(accountPublicId, subject.nsid)}
                        className="mt-2 inline-block text-xs text-cyan-700 hover:underline"
                    >
                        Open contact page
                    </Link>
                ) : null}
            </div>

            <div className="flex min-h-0 flex-1 flex-col p-4" onWheel={(event) => event.stopPropagation()}>
                {actions ? <div className="mb-4 flex shrink-0 flex-wrap items-center gap-3">{actions}</div> : null}

                {subject.note_preview ? (
                    <p className="mb-4 shrink-0 rounded-md bg-slate-100 px-3 py-2 text-sm text-slate-700">
                        {subject.note_preview}
                    </p>
                ) : null}

                <ContactPhotoStrip ownerNsid={subject.nsid} photosCount={subject.photos_count} />

                {!subject.is_root && expandHint ? (
                    <p className="mt-3 shrink-0 text-xs text-slate-500">{expandHint}</p>
                ) : null}
            </div>
        </div>
    );
}
