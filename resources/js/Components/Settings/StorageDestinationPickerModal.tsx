import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';

interface StorageDestinationPickerModalProps {
    open: boolean;
    onClose: () => void;
    configuredOAuthProviders: Set<string>;
    onCreateOAuth: (provider: string) => void;
    onConnectOAuth: (provider: string) => void;
    onAddR2: () => void;
}

export default function StorageDestinationPickerModal({
    open,
    onClose,
    configuredOAuthProviders,
    onCreateOAuth,
    onConnectOAuth,
    onAddR2,
}: StorageDestinationPickerModalProps) {
    return (
        <Modal open={open} onClose={onClose} titleId="storage-picker-title" size="sm">
            <Modal.Header title="Add storage destination" />
            <Modal.Body className="space-y-4">
                <p className="text-sm text-slate-600">
                    Choose a provider. OAuth destinations need client credentials once; R2 uses per-account API keys.
                </p>
                <div className="flex flex-col gap-2">
                    {!configuredOAuthProviders.has('google_photos') ? (
                        <Button type="button" variant="secondary" onClick={() => onCreateOAuth('google_photos')}>
                            Google Photos (OAuth credentials)
                        </Button>
                    ) : (
                        <Button type="button" variant="secondary" onClick={() => onConnectOAuth('google_photos')}>
                            Connect Google Photos
                        </Button>
                    )}
                    {!configuredOAuthProviders.has('google') ? (
                        <Button type="button" variant="secondary" onClick={() => onCreateOAuth('google')}>
                            Google Drive (OAuth credentials)
                        </Button>
                    ) : (
                        <Button type="button" variant="secondary" onClick={() => onConnectOAuth('google')}>
                            Connect Google Drive
                        </Button>
                    )}
                    {!configuredOAuthProviders.has('onedrive') ? (
                        <Button type="button" variant="secondary" onClick={() => onCreateOAuth('onedrive')}>
                            OneDrive (OAuth credentials)
                        </Button>
                    ) : (
                        <Button type="button" variant="secondary" onClick={() => onConnectOAuth('onedrive')}>
                            Connect OneDrive
                        </Button>
                    )}
                    <Button type="button" variant="secondary" onClick={onAddR2}>
                        Cloudflare R2
                    </Button>
                </div>
            </Modal.Body>
        </Modal>
    );
}
