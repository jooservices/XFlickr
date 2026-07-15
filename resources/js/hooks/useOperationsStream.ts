import {
    createContext,
    createElement,
    type ReactNode,
    useCallback,
    useContext,
    useEffect,
    useRef,
    useState,
} from 'react';

import { usePolledResource } from '@/hooks/usePolledResource';
import { getEcho, isReverbConfigured } from '@/lib/echo';
import {
    EMPTY_DEPENDENCIES,
    EMPTY_OVERVIEW,
    applyBatchPatch,
    applyOverviewPatch,
    applySnapshot,
    type DownloadTransferBatch,
    type OperationsStreamState,
} from '@/lib/operationsStreamPatches';
import type { OperationsSnapshotPayload, TransferBatch } from '@/types';

export type { DownloadTransferBatch };

const POLL_INTERVAL_MS = 5000;
const DISCONNECT_FALLBACK_MS = 15_000;

const OperationsStreamContext = createContext<OperationsStreamState | null>(null);

function useOperationsStreamState(): OperationsStreamState {
    const [state, setState] = useState<OperationsStreamState>({
        overview: EMPTY_OVERVIEW,
        queues: {},
        targetBreakdown: [],
        spider: [],
        dependencies: EMPTY_DEPENDENCIES,
        databases: null,
        accounts: [],
        fetchRuns: [],
        downloadBatches: [],
        uploadBatches: [],
        activityHistory: [],
        loading: true,
        transport: isReverbConfigured() ? 'connecting' : 'polling',
    });
    const [pollEnabled, setPollEnabled] = useState(! isReverbConfigured());
    const connectedRef = useRef(false);
    const disconnectTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const clearDisconnectTimer = useCallback(() => {
        if (disconnectTimerRef.current) {
            clearTimeout(disconnectTimerRef.current);
            disconnectTimerRef.current = null;
        }
    }, []);

    const schedulePollFallback = useCallback(() => {
        clearDisconnectTimer();
        disconnectTimerRef.current = setTimeout(() => {
            if (! connectedRef.current) {
                setPollEnabled(true);
                setState((previous) => ({ ...previous, transport: 'polling' }));
            }
        }, DISCONNECT_FALLBACK_MS);
    }, [clearDisconnectTimer]);

    const { data } = usePolledResource<{ data: OperationsSnapshotPayload }>(
        pollEnabled || state.loading ? '/api/v1/operations/snapshot' : null,
        { intervalMs: POLL_INTERVAL_MS },
    );

    useEffect(() => {
        if (! data?.data) {
            return;
        }

        setState((previous) => applySnapshot(previous, data.data));
    }, [data]);

    useEffect(() => {
        if (! isReverbConfigured()) {
            return;
        }

        const echo = getEcho();
        if (! echo) {
            setPollEnabled(true);
            setState((previous) => ({ ...previous, transport: 'polling' }));

            return;
        }

        const channel = echo.private('operations');

        const markConnected = () => {
            connectedRef.current = true;
            clearDisconnectTimer();
            setPollEnabled(false);
            setState((previous) => ({ ...previous, transport: 'websocket' }));
            void fetch('/api/v1/operations/snapshot', { credentials: 'same-origin' })
                .then((response) => response.json())
                .then((body: { data?: OperationsSnapshotPayload }) => {
                    if (body?.data) {
                        setState((previous) => applySnapshot(previous, body.data as OperationsSnapshotPayload));
                    }
                })
                .catch(() => {
                    setPollEnabled(true);
                });
        };

        channel.subscribed(markConnected);
        channel.error(() => {
            connectedRef.current = false;
            schedulePollFallback();
        });

        channel.listen('.ops.batch.updated', (payload: { batch?: TransferBatch & { sample_error?: string | null } }) => {
            if (! payload?.batch) {
                return;
            }

            setState((previous) => applyBatchPatch(previous, payload.batch as TransferBatch));
        });

        channel.listen(
            '.ops.overview.changed',
            (payload: {
                overview?: OperationsSnapshotPayload['overview'];
                queues?: OperationsSnapshotPayload['queues'];
            }) => {
                setState((previous) => applyOverviewPatch(previous, payload.overview ?? {}, payload.queues));
            },
        );

        schedulePollFallback();

        return () => {
            clearDisconnectTimer();
            echo.leave('private-operations');
            connectedRef.current = false;
        };
    }, [clearDisconnectTimer, schedulePollFallback]);

    return state;
}

export function OperationsStreamProvider({ children }: { children: ReactNode }) {
    const value = useOperationsStreamState();

    return createElement(OperationsStreamContext.Provider, { value }, children);
}

export function useOperationsStream(): OperationsStreamState {
    const ctx = useContext(OperationsStreamContext);

    if (! ctx) {
        throw new Error('useOperationsStream must be used within OperationsStreamProvider');
    }

    return ctx;
}
