import { usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useState } from 'react';

import Button from '@/Components/ui/Button';
import Checkbox from '@/Components/ui/Checkbox';
import Modal from '@/Components/ui/Modal';
import type { PageProps } from '@/types';

export interface UploadConfirmPayload {
    deleteLocalAfterUpload: boolean;
}

interface UploadConfirmModalProps {
    open: boolean;
    onClose: () => void;
    onConfirm: (payload: UploadConfirmPayload) => void;
    selectedCount: number;
    isMatching: boolean;
    submitting?: boolean;
}

export default function UploadConfirmModal({
    open,
    onClose,
    onConfirm,
    selectedCount,
    isMatching,
    submitting = false,
}: UploadConfirmModalProps) {
    const { app } = usePage<PageProps>().props;
    const globalDefault = app.delete_local_after_upload ?? false;
    const [deleteLocal, setDeleteLocal] = useState(globalDefault);

    const handleConfirm = () => {
        onConfirm({ deleteLocalAfterUpload: deleteLocal });
    };

    const countLabel = isMatching ? `all ${selectedCount} matching` : `${selectedCount} selected`;

    return (
        <Modal open={open} onClose={onClose} closeDisabled={submitting} titleId="upload-confirm-title" size="md">
            <Modal.Header title="Confirm Upload" />
            <Modal.Body className="space-y-4">
                <p className="text-sm text-slate-700">
                    Upload <strong>{countLabel}</strong> photo(s) to storage.
                </p>

                <label className="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <Checkbox
                        checked={deleteLocal}
                        onChange={(e) => setDeleteLocal(e.target.checked)}
                        className="mt-0.5"
                    />
                    <div className="space-y-1">
                        <span className="text-sm font-medium text-slate-800">
                            Delete local files after upload
                        </span>
                        <p className="text-xs text-slate-500">
                            Remove cached photo files from local storage once they have been
                            successfully uploaded. Files can be re-downloaded from Flickr if needed.
                        </p>
                    </div>
                </label>

                {deleteLocal ? (
                    <div className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600" />
                        <p className="text-xs text-amber-800">
                            Local files will be permanently deleted after each photo is successfully
                            uploaded. To re-upload later, files will need to be downloaded again from Flickr.
                        </p>
                    </div>
                ) : null}
            </Modal.Body>
            <Modal.Footer>
                <Button type="button" variant="secondary" size="sm" onClick={onClose} disabled={submitting}>
                    Cancel
                </Button>
                <Button type="button" variant="primary" size="sm" onClick={handleConfirm} disabled={submitting}>
                    {submitting ? 'Uploading\u2026' : 'Start Upload'}
                </Button>
            </Modal.Footer>
        </Modal>
    );
}
