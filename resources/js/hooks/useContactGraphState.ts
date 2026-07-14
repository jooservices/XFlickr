import { useCallback, useEffect, useMemo, useRef, useState, type MouseEvent, type PointerEvent, type RefObject } from 'react';

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

interface HoverState {
    nsid: string;
    clientX: number;
    clientY: number;
}

export interface UseContactGraphStateOptions {
    accountPublicId: string;
    rootNsid: string;
    onExit: () => void;
}

export interface ContactGraphToolbarState {
    directShown: number;
    directTotal: number;
    nodeCount: number;
    hasMoreDirect: boolean;
    loadMoreStep: number;
    loadingMore: boolean;
    isBrowserFullscreen: boolean;
    currentDirectLimit: number;
    onLoadMore: (nextLimit: number) => void;
    onShowAll: () => void;
    onToggleFullscreen: () => void;
    onExit: () => void;
}

export interface UseContactGraphStateResult {
    shellRef: RefObject<HTMLDivElement | null>;
    canvasContainerRef: (node: HTMLDivElement | null) => void;
    accountPublicId: string;
    loading: boolean;
    error: string | null;
    loadSnapshot: (limit?: number, replace?: boolean) => Promise<void>;
    canvasRef: RefObject<HTMLCanvasElement | null>;
    panZoom: ReturnType<typeof useGraphPanZoom>;
    handlePointerMove: (event: PointerEvent<HTMLCanvasElement>) => void;
    handleClick: (event: MouseEvent<HTMLCanvasElement>) => void;
    clearHovered: () => void;
    hoveredNode: ContactGraphNode | undefined;
    hovered: HoverState | null;
    selectedNode: ContactGraphNode | undefined;
    clearSelected: () => void;
    toolbar: ContactGraphToolbarState;
    handleExpand: (subjectNsid: string) => Promise<void>;
    isExpandingSelected: boolean;
    handleAnnotationUpdated: (payload: ContactAnnotationPayload) => void;
    notes: Record<string, string | null>;
}

export function useContactGraphState({
    accountPublicId,
    rootNsid,
    onExit,
}: UseContactGraphStateOptions): UseContactGraphStateResult {
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
    const { resetTransform } = panZoom;

    useEffect(() => {
        if (loading || error !== null || initialFitAppliedRef.current) {
            return;
        }

        resetTransform(fitTransform);
        initialFitAppliedRef.current = true;
        requestRedraw();
    }, [loading, error, fitTransform, resetTransform, requestRedraw]);

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
        const shellElement = shellRef.current;

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
            setIsBrowserFullscreen(document.fullscreenElement === shellElement);
        }

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', onKeyDown);
        document.addEventListener('fullscreenchange', onFullscreenChange);

        return () => {
            document.body.style.overflow = previousOverflow;
            document.removeEventListener('keydown', onKeyDown);
            document.removeEventListener('fullscreenchange', onFullscreenChange);

            if (document.fullscreenElement === shellElement) {
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

        let cancelled = false;
        let timeoutId = 0;

        const tick = async () => {
            if (cancelled) {
                return;
            }

            for (const [subjectNsid, state] of subjects) {
                await pollDelta(subjectNsid, state).catch(() => undefined);
            }

            if (!cancelled) {
                timeoutId = window.setTimeout(() => {
                    void tick();
                }, 1500);
            }
        };

        void tick();

        return () => {
            cancelled = true;
            window.clearTimeout(timeoutId);
        };
    }, [expanding, pollDelta]);

    const selectedNode = selectedNsid ? nodes.get(selectedNsid) : undefined;
    const hoveredNode = hovered ? nodes.get(hovered.nsid) : undefined;
    const isExpandingSelected = selectedNsid ? expanding[selectedNsid] !== undefined : false;

    const toggleBrowserFullscreen = useCallback(async () => {
        const element = shellRef.current;

        if (!element) {
            return;
        }

        if (document.fullscreenElement) {
            await document.exitFullscreen();
            return;
        }

        await element.requestFullscreen();
    }, []);

    const handleExpand = useCallback(
        async (subjectNsid: string) => {
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
        },
        [accountPublicId, edges],
    );

    const handleAnnotationUpdated = useCallback(
        (payload: ContactAnnotationPayload) => {
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
        },
        [requestRedraw],
    );

    const handleLoadMore = useCallback(
        (nextLimit: number) => {
            void loadSnapshot(nextLimit, true);
        },
        [loadSnapshot],
    );

    const handlePointerMove = useCallback(
        (event: PointerEvent<HTMLCanvasElement>) => {
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
        },
        [hitTest, panZoom],
    );

    const handleClick = useCallback(
        (event: MouseEvent<HTMLCanvasElement>) => {
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
        },
        [hitTest, panZoom],
    );

    const loadMoreStep = meta?.load_more_step ?? 100;
    const directTotal = meta?.direct_total ?? nodeList.length;
    const directShown = meta?.direct_shown ?? nodeList.length;
    const hasMoreDirect = meta?.has_more_direct ?? false;

    const toolbar: ContactGraphToolbarState = useMemo(
        () => ({
            directShown,
            directTotal,
            nodeCount: nodeList.length,
            hasMoreDirect,
            loadMoreStep,
            loadingMore,
            isBrowserFullscreen,
            currentDirectLimit: directLimit ?? directShown,
            onLoadMore: handleLoadMore,
            onShowAll: () => handleLoadMore(0),
            onToggleFullscreen: () => void toggleBrowserFullscreen(),
            onExit,
        }),
        [
            directShown,
            directTotal,
            nodeList.length,
            hasMoreDirect,
            loadMoreStep,
            loadingMore,
            isBrowserFullscreen,
            directLimit,
            handleLoadMore,
            toggleBrowserFullscreen,
            onExit,
        ],
    );

    return {
        shellRef,
        canvasContainerRef: canvasContainer.ref,
        accountPublicId,
        loading,
        error,
        loadSnapshot,
        canvasRef,
        panZoom,
        handlePointerMove,
        handleClick,
        clearHovered: () => setHovered(null),
        hoveredNode,
        hovered,
        selectedNode,
        clearSelected: () => setSelectedNsid(null),
        toolbar,
        handleExpand,
        isExpandingSelected,
        handleAnnotationUpdated,
        notes,
    };
}
