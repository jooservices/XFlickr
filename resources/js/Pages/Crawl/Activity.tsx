import { Head } from '@inertiajs/react';
import { useState } from 'react';

import {
    PageShell,
    PageShellCanvas,
    PageShellIdentity,
} from '@/Components/layout/page-shell';
import ActivityFeedPanel from '@/Components/Operations/ActivityFeedPanel';
import {
    readActivityFeedFiltersFromLocation,
    writeActivityFeedFiltersToLocation,
} from '@/hooks/useActivityFeed';
import AppLayout from '@/Layouts/AppLayout';
import type { ActivityFeedFilters } from '@/lib/activityFeed';

export default function Activity() {
    return (
        <AppLayout>
            <ActivityPageBody />
        </AppLayout>
    );
}

function ActivityPageBody() {
    const [filters, setFilters] = useState<ActivityFeedFilters>(() => {
        const initial = readActivityFeedFiltersFromLocation();
        writeActivityFeedFiltersToLocation(initial);

        return initial;
    });

    return (
        <>
            <Head title="Activity" />

            <PageShell data-testid="activity-page">
                <PageShellIdentity
                    breadcrumbs={[{ label: 'Operations', href: '/operations' }, { label: 'Activity' }]}
                    title="Activity"
                    subtitle="Durable audit and domain trail from ActivityLog. Polling every 15s."
                />

                <PageShellCanvas className="space-y-6" variant="plain">
                    <ActivityFeedPanel filters={filters} onFiltersChange={setFilters} />
                </PageShellCanvas>
            </PageShell>
        </>
    );
}
