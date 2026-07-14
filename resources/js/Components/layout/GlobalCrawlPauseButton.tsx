import { router } from '@inertiajs/react';
import { Pause, Play } from 'lucide-react';
import { useState } from 'react';

import Button from '@/Components/Button';

type GlobalCrawlPauseButtonProps = {
    paused: boolean;
};

export default function GlobalCrawlPauseButton({ paused }: GlobalCrawlPauseButtonProps) {
    const [busy, setBusy] = useState(false);

    function toggle(): void {
        if (busy) {
            return;
        }

        setBusy(true);
        router.post(
            '/settings/crawl-pause',
            { paused: paused ? 0 : 1 },
            {
                preserveScroll: true,
                onFinish: () => setBusy(false),
            },
        );
    }

    return (
        <Button
            type="button"
            size="sm"
            variant={paused ? 'warning' : 'secondary'}
            disabled={busy}
            onClick={toggle}
            aria-pressed={paused}
            aria-label={paused ? 'Resume crawls' : 'Pause crawls'}
            title={paused ? 'Resume crawls' : 'Pause crawls'}
        >
            {paused ? <Play className="h-4 w-4" /> : <Pause className="h-4 w-4" />}
            <span className="hidden sm:inline">{paused ? 'Resume' : 'Pause'}</span>
        </Button>
    );
}
