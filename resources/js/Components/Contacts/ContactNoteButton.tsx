import { FileText } from 'lucide-react';
import { useEffect, useState } from 'react';

import Button from '@/Components/ui/Button';
import { cn } from '@/lib/cn';

interface ContactNoteButtonProps {
    note: string | null;
    disabled?: boolean;
    onSave: (note: string) => Promise<void>;
    size?: 'sm' | 'md';
}

export default function ContactNoteButton({
    note,
    disabled = false,
    onSave,
    size = 'sm',
}: ContactNoteButtonProps) {
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState(note ?? '');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (open) {
            setDraft(note ?? '');
        }
    }, [note, open]);

    const hasNote = (note ?? '').trim().length > 0;

    async function handleSave() {
        setSaving(true);

        try {
            await onSave(draft);
            setOpen(false);
        } finally {
            setSaving(false);
        }
    }

    return (
        <div className="relative">
            <Button
                type="button"
                variant="ghost"
                size={size}
                disabled={disabled}
                aria-label={hasNote ? 'Edit note' : 'Add note'}
                title={hasNote ? 'Edit note' : 'Add note'}
                onClick={() => setOpen((current) => !current)}
                className={cn(hasNote ? 'text-cyan-700 hover:text-cyan-800' : 'text-slate-400 hover:text-cyan-700')}
            >
                <FileText className="h-4 w-4" />
            </Button>

            {open ? (
                <div className="absolute right-0 z-20 mt-1 w-72 rounded-lg border border-slate-200 bg-white p-3 shadow-lg">
                    <label className="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">
                        Note
                    </label>
                    <textarea
                        value={draft}
                        onChange={(event) => setDraft(event.target.value)}
                        rows={4}
                        maxLength={4000}
                        placeholder="Your private note about this contact…"
                        className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-900 focus:border-cyan-600 focus:outline-none focus:ring-1 focus:ring-cyan-600"
                    />
                    <div className="mt-2 flex justify-end gap-2">
                        <Button type="button" variant="secondary" size="sm" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="button" variant="primary" size="sm" disabled={saving} onClick={() => void handleSave()}>
                            {saving ? 'Saving…' : 'Save'}
                        </Button>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
