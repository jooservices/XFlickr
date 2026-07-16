import { DownloadCloud, FilePlus2, Trash2 } from 'lucide-react';
import { useMemo } from 'react';

import PhotoMembershipLinks from '@/Components/Catalog/PhotoMembershipLinks';
import type { BulkAction } from '@/Components/ui/BulkActionBar';
import Button from '@/Components/ui/Button';
import DataTable from '@/Components/ui/DataTable';
import EmptyState from '@/Components/ui/EmptyState';
import PageSection from '@/Components/ui/PageSection';
import type { IntegrityFixAction, IntegrityReport, StoredFileAnomaly } from '@/hooks/useIntegrityReport';
import { useTableSelection } from '@/hooks/useTableSelection';

function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 Bytes';

    const unit = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const index = Math.floor(Math.log(bytes) / Math.log(unit));

    return `${parseFloat((bytes / Math.pow(unit, index)).toFixed(2))} ${sizes[index]}`;
}

interface SyncIntegrityPanelProps {
    report: IntegrityReport | null;
    loading: boolean;
    fixing: boolean;
    onFix: (actions: IntegrityFixAction[]) => void;
}

export default function SyncIntegrityPanel({
    report,
    loading,
    fixing,
    onFix,
}: SyncIntegrityPanelProps) {
    const orphanedFiles = report?.orphaned_files ?? [];
    const missingFiles = report?.missing_files ?? [];

    const orphanRowKey = (file: StoredFileAnomaly) => file.path ?? file.source_id;
    const missingRowKey = (file: StoredFileAnomaly) => String(file.id ?? file.source_id);

    const orphanSelection = useTableSelection({
        rowKey: orphanRowKey,
        rows: orphanedFiles,
        clearWhen: orphanedFiles.map(orphanRowKey).join(','),
    });

    const missingSelection = useTableSelection({
        rowKey: missingRowKey,
        rows: missingFiles,
        clearWhen: missingFiles.map(missingRowKey).join(','),
    });

    const clearOrphanSelection = orphanSelection.clear;
    const clearMissingSelection = missingSelection.clear;

    const orphanBulkActions = useMemo<BulkAction<StoredFileAnomaly>[]>(
        () => [
            {
                id: 'import',
                label: 'Import',
                icon: <FilePlus2 className="h-3.5 w-3.5" />,
                variant: 'secondary',
                disabled: () => fixing,
                onAction: ({ selectedRows }) => {
                    void onFix(
                        selectedRows
                            .map((file) => ({ type: 'import' as const, id: file.id })),
                    );
                    clearOrphanSelection();
                },
            },
            {
                id: 'delete',
                label: 'Delete',
                icon: <Trash2 className="h-3.5 w-3.5" />,
                variant: 'destructive',
                disabled: () => fixing,
                onAction: ({ selectedRows }) => {
                    void onFix(
                        selectedRows
                            .map((file) => ({ type: 'delete' as const, id: file.id })),
                    );
                    clearOrphanSelection();
                },
            },
        ],
        [clearOrphanSelection, fixing, onFix],
    );

    const missingBulkActions = useMemo<BulkAction<StoredFileAnomaly>[]>(
        () => [
            {
                id: 'redownload',
                label: 'Re-download',
                icon: <DownloadCloud className="h-3.5 w-3.5" />,
                variant: 'secondary',
                disabled: () => fixing,
                onAction: ({ selectedRows }) => {
                    void onFix(
                        selectedRows
                            .map((file) => ({ type: 'redownload' as const, id: file.id })),
                    );
                    clearMissingSelection();
                },
            },
        ],
        [clearMissingSelection, fixing, onFix],
    );

    return (
        <div className="space-y-8" data-testid="sync-integrity-panel">
            <PageSection
                title="Orphaned Disk Files"
                description="Files present in local storage that are not registered in the database."
            >
                <DataTable
                    busy={loading}
                    busyLabel="Loading orphaned files…"
                    columns={[
                        {
                            key: 'photo',
                            label: 'Photo ID',
                            render: (file) => <span className="font-mono text-xs">{file.source_id}</span>,
                        },
                        {
                            key: 'owner',
                            label: 'Owner',
                            render: (file) => (
                                <span className="font-mono text-xs text-slate-600">
                                    {file.source_owner ?? '—'}
                                </span>
                            ),
                        },
                        {
                            key: 'photosets',
                            label: 'Photosets',
                            render: (file) => (
                                <PhotoMembershipLinks items={file.photosets ?? []} kind="photoset" />
                            ),
                        },
                        {
                            key: 'galleries',
                            label: 'Galleries',
                            render: (file) => (
                                <PhotoMembershipLinks items={file.galleries ?? []} kind="gallery" />
                            ),
                        },
                        {
                            key: 'path',
                            label: 'Local Path',
                            render: (file) => (
                                <span className="font-mono text-xs text-slate-500">{file.path ?? '—'}</span>
                            ),
                        },
                        {
                            key: 'size',
                            label: 'File Size',
                            render: (file) => formatBytes(file.size || 0),
                        },
                    ]}
                    data={orphanedFiles}
                    rowKey={orphanRowKey}
                    emptyMessage="No orphaned disk files found."
                    selection={orphanedFiles.length > 0 ? orphanSelection.tableSelection : undefined}
                    bulkActions={orphanedFiles.length > 0 ? orphanBulkActions : undefined}
                    onBulkClear={orphanSelection.clear}
                    matchingLabel="orphaned files"
                    actionsColumn={(file) => (
                        <div className="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                disabled={fixing}
                                onClick={() => void onFix([{ type: 'import', id: file.id }])}
                                icon={<FilePlus2 className="h-3.5 w-3.5" />}
                            >
                                Import
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                disabled={fixing}
                                onClick={() => void onFix([{ type: 'delete', id: file.id }])}
                                icon={<Trash2 className="h-3.5 w-3.5" />}
                            >
                                Delete
                            </Button>
                        </div>
                    )}
                />
            </PageSection>

            <PageSection
                title="Missing Local Files"
                description="Completed download records whose physical files are missing from disk."
            >
                {missingFiles.length === 0 ? (
                    <EmptyState title="No missing local files found." />
                ) : (
                    <DataTable
                        busy={loading}
                        busyLabel="Loading missing files…"
                        columns={[
                            {
                                key: 'photo',
                                label: 'Photo ID',
                                render: (file) => <span className="font-mono text-xs">{file.source_id}</span>,
                            },
                            {
                                key: 'owner',
                                label: 'Owner',
                                render: (file) => (
                                    <span className="font-mono text-xs text-slate-600">
                                        {file.source_owner ?? '—'}
                                    </span>
                                ),
                            },
                            {
                                key: 'photosets',
                                label: 'Photosets',
                                render: (file) => (
                                    <PhotoMembershipLinks items={file.photosets ?? []} kind="photoset" />
                                ),
                            },
                            {
                                key: 'galleries',
                                label: 'Galleries',
                                render: (file) => (
                                    <PhotoMembershipLinks items={file.galleries ?? []} kind="gallery" />
                                ),
                            },
                            {
                                key: 'path',
                                label: 'Expected Path',
                                render: (file) => (
                                    <span className="font-mono text-xs text-slate-500">
                                        {file.local_path ?? '—'}
                                    </span>
                                ),
                            },
                        ]}
                        data={missingFiles}
                        rowKey={missingRowKey}
                        emptyMessage="No missing local files found."
                        selection={missingSelection.tableSelection}
                        bulkActions={missingBulkActions}
                        onBulkClear={missingSelection.clear}
                        matchingLabel="missing files"
                        actionsColumn={(file) => (
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                disabled={fixing}
                                onClick={() => void onFix([{ type: 'redownload', id: file.id }])}
                                icon={<DownloadCloud className="h-3.5 w-3.5" />}
                            >
                                Re-download
                            </Button>
                        )}
                    />
                )}
            </PageSection>
        </div>
    );
}
