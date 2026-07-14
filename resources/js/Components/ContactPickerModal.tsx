import Button from '@/Components/Button';
import Checkbox from '@/Components/Checkbox';
import Modal from '@/Components/Modal';
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
    const toggle = (nsid: string) => {
        if (selected.includes(nsid)) {
            onChange(selected.filter((id) => id !== nsid));
        } else {
            onChange([...selected, nsid]);
        }
    };

    return (
        <Modal open={open} onClose={onClose} titleId="contact-picker-title" size="md">
            <Modal.Header title={title} />
            <Modal.Body className="divide-y divide-slate-100 p-0">
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
            </Modal.Body>
            <Modal.Footer>
                <Button type="button" variant="secondary" onClick={onClose}>
                    Cancel
                </Button>
                <Button type="button" variant="primaryDark" onClick={onConfirm} disabled={selected.length === 0}>
                    Confirm ({selected.length})
                </Button>
            </Modal.Footer>
        </Modal>
    );
}
