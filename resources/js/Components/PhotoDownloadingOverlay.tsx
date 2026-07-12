import { Loader2 } from 'lucide-react';

export default function PhotoDownloadingOverlay() {
    return (
        <div className="rounded-full bg-black/50 p-2 text-white" aria-label="Downloading">
            <Loader2 className="size-5 animate-spin" />
        </div>
    );
}
