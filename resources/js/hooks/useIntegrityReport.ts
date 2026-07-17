import { useCallback, useEffect, useState } from 'react';

import { apiGet, apiPost } from '@/lib/apiClient';
import type { PhotoMembership } from '@/types';

export interface StoredFileAnomaly {
    id: string;
    type: 'orphaned' | 'missing';
    source_id: string;
    connection_key?: string | null;
    source_owner?: string | null;
    local_path?: string | null;
    path?: string | null;
    size?: number;
    photosets?: PhotoMembership[];
    galleries?: PhotoMembership[];
}

interface Scan {
    id: string;
    status: 'pending' | 'running' | 'completed' | 'failed';
    orphaned_count: number;
    missing_count: number;
    error_message?: string | null;
}

export interface IntegrityReport {
    checked_at: string;
    orphaned_files: StoredFileAnomaly[];
    missing_files: StoredFileAnomaly[];
    stats: { orphaned_count: number; missing_count: number; total_disk_files: number; total_disk_size: number; total_db_records: number };
}

export type IntegrityFixAction = { type: 'delete' | 'import' | 'redownload'; id: string };

export function useIntegrityReport() {
    const [scan, setScan] = useState<Scan | null>(null);
    const [report, setReport] = useState<IntegrityReport | null>(null);
    const [loading] = useState(false);
    const [scanning, setScanning] = useState(false);
    const [fixing, setFixing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const loadReport = useCallback(async () => {
        if (!scan) return;
        try {
            const status = await apiGet<{ data: Scan }>(`/api/v1/transfers/integrity-scans/${scan.id}`);
            setScan(status.data);
            if (status.data.status !== 'completed') return;
            const anomalies = await apiGet<{ data: StoredFileAnomaly[] }>(`/api/v1/transfers/integrity-scans/${scan.id}/anomalies`);
            const orphaned = anomalies.data.filter((item) => item.type === 'orphaned');
            const missing = anomalies.data.filter((item) => item.type === 'missing');
            setReport({ checked_at: new Date().toISOString(), orphaned_files: orphaned, missing_files: missing, stats: { orphaned_count: status.data.orphaned_count, missing_count: status.data.missing_count, total_disk_files: 0, total_disk_size: 0, total_db_records: 0 } });
            setError(null);
        } catch {
            setError('Unable to load the integrity scan. Try again.');
        }
    }, [scan]);

    useEffect(() => {
        if (!scan || ['completed', 'failed'].includes(scan.status)) return;
        const timer = window.setTimeout(() => void loadReport(), 1500);
        return () => window.clearTimeout(timer);
    }, [loadReport, scan]);

    const triggerScan = useCallback(async () => {
        setScanning(true);
        try {
            const response = await apiPost<{ data: Scan }>('/api/v1/transfers/integrity-scans');
            setScan(response.data);
            setReport(null);
            setError(null);
        } catch {
            setError('Unable to start the integrity scan. Try again.');
        } finally { setScanning(false); }
    }, []);

    const executeFixActions = useCallback(async (actions: IntegrityFixAction[]) => {
        if (!scan || actions.length === 0) return;
        setFixing(true);
        try {
            for (const type of ['delete', 'import', 'redownload'] as const) {
                const ids = actions.filter((action) => action.type === type).map((action) => action.id);
                if (ids.length > 0) await apiPost(`/api/v1/transfers/integrity-scans/${scan.id}/resolutions`, { resolution: type, anomaly_ids: ids });
            }
            await loadReport();
            setError(null);
        } catch {
            setError('Unable to apply the selected integrity resolution. Try again.');
        } finally { setFixing(false); }
    }, [loadReport, scan]);

    return { report, loading, scanning, fixing, error, loadReport, triggerScan, executeFixActions };
}
