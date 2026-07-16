import { RefreshCw } from 'lucide-react';

import Button from '@/Components/ui/Button';
import LoadingIndicator from '@/Components/ui/LoadingIndicator';
import MetricCard from '@/Components/ui/MetricCard';
import PageSection from '@/Components/ui/PageSection';
import type { IntegrityReport } from '@/hooks/useIntegrityReport';

function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 Bytes';

    const unit = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const index = Math.floor(Math.log(bytes) / Math.log(unit));

    return `${parseFloat((bytes / Math.pow(unit, index)).toFixed(2))} ${sizes[index]}`;
}

interface SyncIntegritySummaryProps {
    report: IntegrityReport | null;
    loading: boolean;
    scanning: boolean;
    onScan: () => void;
}

export default function SyncIntegritySummary({
    report,
    loading,
    scanning,
    onScan,
}: SyncIntegritySummaryProps) {
    if (loading && !report) {
        return (
            <div className="flex h-24 items-center justify-center rounded-md border border-slate-200 bg-white">
                <LoadingIndicator label="Loading storage statistics…" />
            </div>
        );
    }

    const stats = report?.stats ?? {
        total_disk_files: 0,
        total_disk_size: 0,
        total_db_records: 0,
        orphaned_count: 0,
        missing_count: 0,
    };
    const hasMismatches = stats.orphaned_count > 0 || stats.missing_count > 0;
    const checkedAt = report?.checked_at ? new Date(report.checked_at).toLocaleTimeString() : 'Never';

    return (
        <div data-testid="sync-integrity-summary">
        <PageSection
            title="Storage Summary"
            description="Local files and completed download records from the latest integrity scan."
        >
            <div className="mb-3 flex justify-end">
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    disabled={scanning}
                    onClick={onScan}
                    icon={<RefreshCw className={`h-3 w-3 ${scanning ? 'animate-spin' : ''}`} />}
                >
                    Scan Now
                </Button>
            </div>
            <div className="grid gap-4 md:grid-cols-3">
                <MetricCard
                    label="Local Storage Cache"
                    value={formatBytes(stats.total_disk_size)}
                    hint={`${stats.total_disk_files.toLocaleString()} files on disk`}
                />
                <MetricCard
                    label="Database Catalog"
                    value={stats.total_db_records.toLocaleString()}
                    hint="Completed download records"
                />
                <MetricCard
                    label="Integrity Health"
                    value={hasMismatches ? 'Mismatches Found' : 'In Sync'}
                    hint={
                        hasMismatches
                            ? `${stats.orphaned_count.toLocaleString()} orphaned · ${stats.missing_count.toLocaleString()} missing · Last scan: ${checkedAt}`
                            : `Last scan: ${checkedAt}`
                    }
                    tone={hasMismatches ? 'rose' : 'emerald'}
                />
            </div>
        </PageSection>
        </div>
    );
}
