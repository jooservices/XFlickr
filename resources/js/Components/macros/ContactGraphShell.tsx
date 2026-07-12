import { GitBranchPlus, Loader2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState, type MouseEvent, type PointerEvent } from 'react';

import Button from '@/Components/Button';
import ContactAnnotationActions from '@/Components/Contacts/ContactAnnotationActions';
import ContactDetailPanel from '@/Components/Contacts/ContactDetailPanel';
import ContactGraphDetailShell from '@/Components/Contacts/ContactGraphDetailShell';
import ContactGraphHoverPopup from '@/Components/Contacts/ContactGraphHoverPopup';
import ContactGraphToolbar from '@/Components/macros/ContactGraphToolbar';
import { drawContactGraph, useContactGraphCanvas } from '@/hooks/useContactGraphCanvas';
import { useElementSize } from '@/hooks/useElementSize';
import { useGraphPanZoom } from '@/hooks/useGraphPanZoom';
import { apiGet, apiPost } from '@/lib/apiClient';
import {
    initialPanForBounds,
    initialZoomForBounds,
    simulateContactGraph,
    type SimulatedGraphNode,
} from '@/lib/contactGraphForceLayout';
import { mergeContactGraphEdges, mergeContactGraphNodes } from '@/lib/contactGraphMerge';
import { flickrApiAccountPath } from '@/lib/flickrAccount';
import type {
    ContactAnnotationPayload,
    ContactGraphDelta,
    ContactGraphEdge,
    ContactGraphExpandResult,
    ContactGraphMeta,
    ContactGraphNode,
    ContactGraphSnapshot,
} from '@/types';

interface ExpandingState {
    crawlRunId: number;
    sinceEdgeId: number;
}

export interface ContactGraphShellProps {
    accountPublicId: string;
    rootNsid: string;
    accountLabel: string;
    onExit: () => void;
}

interface HoverState {
    nsid: string;
    clientX: number;
    clientY: number;
}

export default function ContactGraphShell({
    accountPublicId,
    rootNsid,
    accountLabel,
    onExit,
}: ContactGraphShellProps) {
    const shellRef = useRef<HTMLDivElement>(null);
    const canvasContainer = useElementSize<HTMLDivElement>();
    const viewport = useMemo(
        () => ({ width: canvasContainer.width, height: canvasContainer.height }),
        [canvasContainer.width, canvasContainer.height],
    );

    const [nodes, setNodes] = useState<Map<string, ContactGraphNode>>(new Map());
    const [edges, setEdges] = useState<ContactGraphEdge[]>([]);
    const [meta, setMeta] = useState<ContactGraphMeta | null>(null);
    const [directLimit, setDirectLimit] = useState<number | null>(null);
    const [loading, setLoading] = useState(true);
    const [loadingMore, setLoadingMore] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [selectedNsid, setSelectedNsid] = useState<string | null>(null);
    const [hovered, setHovered] = useState<HoverState | null>(null);
    const [expanding, setExpanding] = useState<Record<string, ExpandingState>>({});
    const [notes, setNotes] = useState<Record<string, string | null>>({});
    const [isBrowserFullscreen, setIsBrowserFullscreen] = useState(false);
    const [renderTick, setRenderTick] = useState(0);

    const positionCacheRef = useRef<Map<string, { x: number; y: number }>>(new Map());
    const initialFitAppliedRef = useRef(false);
    const layoutNodesRef = useRef<SimulatedGraphNode[]>([]);

    const nodeList = useMemo(() => [...nodes.values()], [nodes]);
    const expandingNsids = useMemo(() => new Set(Object.keys(expanding)), [expanding]);

    const layout = useMemo(
        () =>
            simulateContactGraph(
                rootNsid,
                nodeList,
                edges,
                viewport,
                positionCacheRef.current.size > 0 ? positionCacheRef.current : undefined,
            ),
        [rootNsid, nodeList, edges, viewport],
    );

    useEffect(() => {
        const nextCache = new Map<string, { x: number; y: number }>();

        for (const node of layout.nodes) {
            nextCache.set(node.nsid, { x: node.x, y: node.y });
        }

        positionCacheRef.current = nextCache;
        layoutNodesRef.current = layout.nodes;
    }, [layout.nodes]);

    const fitTransform = useMemo(() => {
        const zoom = initialZoomForBounds(layout.bounds, viewport);
        const pan = initialPanForBounds(layout.bounds, viewport, zoom);

        return { x: pan.x, y: pan.y, k: zoom };
    }, [layout.bounds, viewport]);

    const { canvasRef, scheduleDraw, hitTest, buildHighlightedNsids } = useContactGraphCanvas();

    const requestRedraw = useCallback(() => {
        setRenderTick((value) => value + 1);
    }, []);

    const panZoom = useGraphPanZoom({
        enabled: !loading && error === null,
        initialTransform: fitTransform,
        onTransformChange: requestRedraw,
    });

    useEffect(() => {
        if (loading || error !== null || initialFitAppliedRef.current) {
            return;
        }

        panZoom.resetTransform(fitTransform);
        initialFitAppliedRef.current = true;
        requestRedraw();
    }, [loading, error, fitTransform, panZoom.resetTransform, requestRedraw]);

    const highlightedNsids = useMemo(
        () => buildHighlightedNsids(edges, hovered?.nsid ?? selectedNsid),
        [buildHighlightedNsids, edges, hovered?.nsid, selectedNsid],
    );

    useEffect(() => {
        const canvas = canvasRef.current;

        if (!canvas || loading || error) {
            return;
        }

        const ctx = canvas.getContext('2d');

        if (!ctx) {
            return;
        }

        const dpr = window.devicePixelRatio || 1;
        canvas.width = Math.floor(viewport.width * dpr);
        canvas.height = Math.floor(viewport.height * dpr);
        canvas.style.width = `${viewport.width}px`;
        canvas.style.height = `${viewport.height}px`;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        scheduleDraw(() => {
            drawContactGraph({
                ctx,
                width: viewport.width,
                height: viewport.height,
                transform: panZoom.transformRef.current,
                nodes: layoutNodesRef.current,
                edges,
                selectedNsid,
                hoveredNsid: hovered?.nsid ?? null,
                expandingNsids,
                highlightedNsids,
            });
        });
    }, [
        canvasRef,
        loading,
        error,
        viewport.width,
        viewport.height,
        renderTick,
        edges,
        selectedNsid,
        hovered?.nsid,
        expandingNsids,
        highlightedNsids,
        scheduleDraw,
        panZoom.transformRef,
    ]);

    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if (event.key !== 'Escape') {
                return;
            }

            event.preventDefault();

            if (document.fullscreenElement) {
                void document.exitFullscreen();
                return;
            }

            if (selectedNsid) {
                setSelectedNsid(null);
                return;
            }

            onExit();
        }

        function onFullscreenChange() {
            setIsBrowserFullscreen(document.fullscreenElement === shellRef.current);
        }

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', onKeyDown);
        document.addEventListener('fullscreenchange', onFullscreenChange);

        return () => {
            document.body.style.overflow = previousOverflow;
            document.removeEventListener('keydown', onKeyDown);
            document.removeEventListener('fullscreenchange', onFullscreenChange);

            const activeShell = shellRef.current;
            if (document.fullscreenElement === activeShell) {
                void document.exitFullscreen();
            }
        };
    }, [onExit, selectedNsid]);

    const applySnapshot = useCallback((snapshot: ContactGraphSnapshot, replace: boolean) => {
        setNodes((current) =>
            replace ? new Map(snapshot.nodes.map((node) => [node.nsid, node])) : mergeContactGraphNodes(current, snapshot.nodes),
        );
        setEdges((current) => (replace ? snapshot.edges : mergeContactGraphEdges(current, snapshot.edges)));
        setMeta(snapshot.meta);
        setDirectLimit(snapshot.meta.direct_shown);
    }, []);

    const loadSnapshot = useCallback(
        async (limit?: number, replace = true) => {
            if (replace) {
                setLoading(true);
            } else {
                setLoadingMore(true);
            }

            setError(null);

            try {
                const params =
                    limit !== undefined
                        ? { params: { direct_limit: limit } }
                        : undefined;

                const response = await apiGet<{ data: ContactGraphSnapshot }>(
                    flickrApiAccountPath(accountPublicId, '/contact-graph'),
                    params,
                );
                const snapshot = response.data;

                applySnapshot(snapshot, replace);

                if (replace) {
                    setSelectedNsid(null);
                    positionCacheRef.current = new Map();
                    initialFitAppliedRef.current = false;

                    const initialNotes = Object.fromEntries(
                        snapshot.nodes
                            .filter((node) => !node.is_root)
                            .map((node) => [node.nsid, node.note_preview ?? null]),
                    );
                    setNotes(initialNotes);
                }
            } catch {
                setError('Unable to load contact graph.');
            } finally {
                setLoading(false);
                setLoadingMore(false);
            }
        },
        [accountPublicId, applySnapshot],
    );

    useEffect(() => {
        void loadSnapshot();
    }, [loadSnapshot]);

    const pollDelta = useCallback(
        async (subjectNsid: string, state: ExpandingState) => {
            const response = await apiGet<{ data: ContactGraphDelta }>(
                flickrApiAccountPath(accountPublicId, '/contact-graph/delta'),
                {
                    params: {
                        subject_nsid: subjectNsid,
                        since_edge_id: state.sinceEdgeId,
                        crawl_run_id: state.crawlRunId,
                    },
                },
            );
            const delta = response.data;

            if (delta.nodes.length > 0) {
                setNodes((current) => mergeContactGraphNodes(current, delta.nodes));
            }

            if (delta.edges.length > 0) {
                setEdges((current) => mergeContactGraphEdges(current, delta.edges));
            }

            setExpanding((current) => {
                const next = { ...current };
                const stillRunning = !delta.done;

                if (stillRunning) {
                    next[subjectNsid] = {
                        crawlRunId: state.crawlRunId,
                        sinceEdgeId: delta.max_edge_id,
                    };
                } else {
                    delete next[subjectNsid];
                }

                return next;
            });

            requestRedraw();
        },
        [accountPublicId, requestRedraw],
    );

    useEffect(() => {
        const subjects = Object.entries(expanding);

        if (subjects.length === 0) {
            return;
        }

        const tick = () => {
            for (const [subjectNsid, state] of subjects) {
                void pollDelta(subjectNsid, state).catch(() => undefined);
            }
        };

        tick();
        const interval = setInterval(tick, 1500);

        return () => clearInterval(interval);
    }, [expanding, pollDelta]);

    const selectedNode = selectedNsid ? nodes.get(selectedNsid) : undefined;
    const hoveredNode = hovered ? nodes.get(hovered.nsid) : undefined;
    const isExpandingSelected = selectedNsid ? expanding[selectedNsid] !== undefined : false;

    async function toggleBrowserFullscreen() {
        const element = shellRef.current;

        if (!element) {
            return;
        }

        if (document.fullscreenElement) {
            await document.exitFullscreen();
            return;
        }

        await element.requestFullscreen();
    }

    async function handleExpand(subjectNsid: string) {
        setExpanding((current) => ({
            ...current,
            [subjectNsid]: current[subjectNsid] ?? { crawlRunId: 0, sinceEdgeId: 0 },
        }));

        try {
            const subjectEdges = edges.filter((edge) => edge.from === subjectNsid);
            const sinceEdgeId = subjectEdges.reduce((max, edge) => Math.max(max, edge.id), 0);

            const result = await apiPost<{ data: ContactGraphExpandResult }>(
                flickrApiAccountPath(accountPublicId, '/contact-graph/expansions'),
                { contact_nsid: subjectNsid },
            );

            setExpanding((current) => ({
                ...current,
                [subjectNsid]: {
                    crawlRunId: result.data.crawl_run_id,
                    sinceEdgeId,
                },
            }));
        } catch {
            setExpanding((current) => {
                const next = { ...current };
                delete next[subjectNsid];

                return next;
            });
        }
    }

    function handleAnnotationUpdated(payload: ContactAnnotationPayload) {
        setNodes((current) => {
            const next = new Map(current);
            const existing = next.get(payload.nsid);

            if (!existing) {
                return current;
            }

            next.set(payload.nsid, {
                ...existing,
                starred: payload.starred,
                note_preview: payload.note && payload.note.length > 80 ? `${payload.note.slice(0, 77)}...` : payload.note,
            });

            return next;
        });

        setNotes((current) => ({
            ...current,
            [payload.nsid]: payload.note,
        }));

        requestRedraw();
    }

    function handleLoadMore(nextLimit: number) {
        void loadSnapshot(nextLimit, true);
    }

    function handlePointerMove(event: PointerEvent<HTMLCanvasElement>) {
        panZoom.onPointerMove(event);

        if (panZoom.isDragging()) {
            return;
        }

        const nearest = hitTest(event.clientX, event.clientY, layoutNodesRef.current, panZoom.transformRef.current);

        if (nearest) {
            setHovered((current) =>
                current?.nsid === nearest.nsid &&
                current.clientX === event.clientX &&
                current.clientY === event.clientY
                    ? current
                    : { nsid: nearest.nsid, clientX: event.clientX, clientY: event.clientY },
            );
        } else {
            setHovered(null);
        }
    }

    function handleClick(event: MouseEvent<HTMLCanvasElement>) {
        if (panZoom.wasDragged()) {
            panZoom.clearDragged();
            return;
        }

        const nearest = hitTest(event.clientX, event.clientY, layoutNodesRef.current, panZoom.transformRef.current);

        if (nearest) {
            setSelectedNsid(nearest.nsid);
        } else {
            setSelectedNsid(null);
        }
    }

    const loadMoreStep = meta?.load_more_step ?? 100;
    const directTotal = meta?.direct_total ?? nodeList.length;
    const directShown = meta?.direct_shown ?? nodeList.length;
    const hasMoreDirect = meta?.has_more_direct ?? false;

    return (
        <div ref={shellRef} className="fixed inset-0 z-[100] flex flex-col bg-slate-100">
            <ContactGraphToolbar
                directShown={directShown}
                directTotal={directTotal}
                nodeCount={nodeList.length}
                hasMoreDirect={hasMoreDirect}
                loadMoreStep={loadMoreStep}
                loadingMore={loadingMore}
                isBrowserFullscreen={isBrowserFullscreen}
                currentDirectLimit={directLimit ?? directShown}
                onLoadMore={handleLoadMore}
                onShowAll={() => handleLoadMore(0)}
                onToggleFullscreen={() => void toggleBrowserFullscreen()}
                onExit={onExit}
            />

            <div className="flex min-h-0 flex-1 overflow-hidden">
                <div ref={canvasContainer.ref} className="relative min-w-0 flex-1 overflow-hidden">
                {loading ? (
                    <div className="flex h-full items-center justify-center gap-2 text-sm text-slate-600">
                        <Loader2 className="h-5 w-5 animate-spin" />
                        Laying out graph…
                    </div>
                ) : error ? (
                    <div className="flex h-full flex-col items-center justify-center gap-3">
                        <p className="text-sm text-rose-700">{error}</p>
                        <Button type="button" variant="secondary" size="sm" onClick={() => void loadSnapshot()}>
                            Retry
                        </Button>
                    </div>
                ) : (
                    <canvas
                        ref={canvasRef}
                        className="h-full w-full cursor-grab active:cursor-grabbing"
                        onWheel={panZoom.onWheel}
                        onPointerDown={panZoom.onPointerDown}
                        onPointerMove={handlePointerMove}
                        onPointerUp={panZoom.onPointerUp}
                        onPointerLeave={(event) => {
                            panZoom.onPointerUp(event);
                            setHovered(null);
                        }}
                        onClick={handleClick}
                    />
                )}

                {hoveredNode && hovered && !loading && !error ? (
                    <ContactGraphHoverPopup
                        node={hoveredNode}
                        accountLabel={accountLabel}
                        clientX={hovered.clientX}
                        clientY={hovered.clientY}
                    />
                ) : null}
                </div>

                {selectedNode && !loading && !error ? (
                    <ContactGraphDetailShell onClose={() => setSelectedNsid(null)}>
                        <ContactDetailPanel
                            key={selectedNode.nsid}
                            accountPublicId={accountPublicId}
                            accountLabel={accountLabel}
                            subject={selectedNode}
                            onClose={() => setSelectedNsid(null)}
                            actions={
                                <>
                                    <Button
                                        type="button"
                                        variant="primary"
                                        size="sm"
                                        disabled={isExpandingSelected}
                                        onClick={() => void handleExpand(selectedNode.nsid)}
                                    >
                                        {isExpandingSelected ? (
                                            <>
                                                <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                                                Expanding…
                                            </>
                                        ) : (
                                            <>
                                                <GitBranchPlus className="mr-1.5 h-4 w-4" />
                                                {selectedNode.child_count > 0
                                                    ? 'Re-expand contacts'
                                                    : 'Expand contacts'}
                                            </>
                                        )}
                                    </Button>

                                    {!selectedNode.is_root ? (
                                        <ContactAnnotationActions
                                            accountPublicId={accountPublicId}
                                            contactNsid={selectedNode.nsid}
                                            starred={selectedNode.starred}
                                            note={notes[selectedNode.nsid] ?? null}
                                            onUpdated={handleAnnotationUpdated}
                                        />
                                    ) : null}
                                </>
                            }
                        />
                    </ContactGraphDetailShell>
                ) : null}
            </div>
        </div>
    );
}
