import ContactNoteButton from '@/Components/Contacts/ContactNoteButton';
import ContactStarButton from '@/Components/Contacts/ContactStarButton';
import { apiPatch } from '@/lib/apiClient';
import { flickrApiAccountPath } from '@/lib/flickrAccount';
import type { ContactAnnotationPayload } from '@/types';

interface ContactAnnotationActionsProps {
    accountPublicId: string;
    contactNsid: string;
    starred: boolean;
    note: string | null;
    disabled?: boolean;
    onUpdated?: (payload: ContactAnnotationPayload) => void;
}

export default function ContactAnnotationActions({
    accountPublicId,
    contactNsid,
    starred,
    note,
    disabled = false,
    onUpdated,
}: ContactAnnotationActionsProps) {
    async function patchAnnotation(body: { note?: string; starred?: boolean }) {
        const payload = await apiPatch<{ data: ContactAnnotationPayload }>(
            flickrApiAccountPath(accountPublicId, `/contacts/${encodeURIComponent(contactNsid)}/annotation`),
            body,
        );

        onUpdated?.(payload.data);
    }

    return (
        <div className="flex items-center gap-0.5">
            <ContactStarButton
                starred={starred}
                disabled={disabled}
                onToggle={() => void patchAnnotation({ starred: !starred })}
            />
            <ContactNoteButton
                note={note}
                disabled={disabled}
                onSave={async (nextNote) => {
                    await patchAnnotation({ note: nextNote });
                }}
            />
        </div>
    );
}
