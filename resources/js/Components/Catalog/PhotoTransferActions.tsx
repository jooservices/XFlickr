import { router } from '@inertiajs/react';
import { Download, Upload } from 'lucide-react';

import Button from '@/Components/ui/Button';
import { flickrAccountPath } from '@/lib/flickrAccount';

interface PhotoTransferActionsProps {
    accountPublicId: string;
    flickrPhotoId: string;
}

export default function PhotoTransferActions({ accountPublicId, flickrPhotoId }: PhotoTransferActionsProps) {
    const startDownload = () => {
        router.post(
            flickrAccountPath(accountPublicId, '/download'),
            { flickr_photo_id: flickrPhotoId },
            { preserveScroll: true },
        );
    };

    const startUpload = () => {
        router.post(
            flickrAccountPath(accountPublicId, '/upload'),
            { flickr_photo_id: flickrPhotoId },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex items-center gap-0.5" onClick={(event) => event.stopPropagation()} onKeyDown={(event) => event.stopPropagation()}>
            <Button
                type="button"
                variant="ghost"
                size="xs"
                aria-label="Download photo"
                className="text-white hover:bg-white/20"
                onClick={startDownload}
            >
                <Download className="size-3.5" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="xs"
                aria-label="Upload photo"
                className="text-white hover:bg-white/20"
                onClick={startUpload}
            >
                <Upload className="size-3.5" />
            </Button>
        </div>
    );
}
