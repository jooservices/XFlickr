import { X } from 'lucide-react';

import Checkbox from '@/Components/Checkbox';
import type { ContactListItem } from '@/types';

interface ContactPickerModalProps {
    open: boolean;
    contacts: ContactListItem[];
    selected: string[];
    onChange: (nsids: string[]) => void;
    onClose: () => void;
    onConfirm: () => void;
    title?: string;
}

export default function ContactPickerModal({
    open,
    contacts,
    selected,
    onChange,
    onClose,
    onConfirm,
    title = 'Select contacts',
}: ContactPickerModalProps) {
    if (!open) {
        return null;
    }

    const toggle = (nsid: string) => {
        if (selected.includes(nsid)) {
            onChange(selected.filter((id) => id !== nsid));
        } else {
            onChange([...selected, nsid]);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
            <div className="max-h-[80vh] w-full max-w-lg overflow-hidden rounded-lg bg-white shadow-xl">
                <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <h3 className="font-semibold text-slate-900">{title}</h3>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                    >
                        <X className="size-4" />
                    </button>
                </div>

                <div className="max-h-96 overflow-y-auto divide-y divide-slate-100">
                    {contacts.length === 0 ? (
                        <p className="px-4 py-8 text-center text-sm text-slate-500">No contacts available.</p>
                    ) : (
                        contacts.map((contact) => (
                            <label
                                key={contact.nsid}
                                className="flex cursor-pointer items-center gap-3 px-4 py-3 hover:bg-slate-50"
                            >
                                <Checkbox
                                    checked={selected.includes(contact.nsid)}
                                    onChange={() => toggle(contact.nsid)}
                                />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium text-slate-900">
                                        {contact.realname || contact.username || contact.nsid}
                                    </p>
                                    <p className="truncate text-xs text-slate-500">@{contact.username ?? contact.nsid}</p>
                                </div>
                            </label>
                        ))
                    )}
                </div>

                <div className="flex justify-end gap-2 border-t border-slate-200 px-4 py-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={selected.length === 0}
                        className="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50"
                    >
                        Confirm ({selected.length})
                    </button>
                </div>
            </div>
        </div>
    );
}
