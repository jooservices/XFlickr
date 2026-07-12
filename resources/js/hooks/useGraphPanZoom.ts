import {
    useCallback,
    useEffect,
    useRef,
    type PointerEvent,
    type RefObject,
    type WheelEvent,
} from 'react';

export interface GraphTransform {
    x: number;
    y: number;
    k: number;
}

interface UseGraphPanZoomOptions {
    enabled: boolean;
    initialTransform?: GraphTransform;
    onTransformChange?: () => void;
}

export function useGraphPanZoom({
    enabled,
    initialTransform,
    onTransformChange,
}: UseGraphPanZoomOptions) {
    const transformRef = useRef<GraphTransform>(initialTransform ?? { x: 0, y: 0, k: 1 });
    const dragging = useRef(false);
    const dragged = useRef(false);
    const lastPoint = useRef({ x: 0, y: 0 });
    const initialApplied = useRef(false);

    const notify = useCallback(() => {
        onTransformChange?.();
    }, [onTransformChange]);

    const setTransform = useCallback(
        (next: GraphTransform) => {
            transformRef.current = next;
            notify();
        },
        [notify],
    );

    useEffect(() => {
        if (!initialTransform) {
            return;
        }

        if (!initialApplied.current) {
            transformRef.current = initialTransform;
            initialApplied.current = true;
            notify();
        }
    }, [initialTransform, notify]);

    const resetTransform = useCallback(
        (next: GraphTransform) => {
            transformRef.current = next;
            notify();
        },
        [notify],
    );

    const onWheel = useCallback(
        (event: WheelEvent<HTMLElement>) => {
            if (!enabled) {
                return;
            }

            event.preventDefault();

            const rect = event.currentTarget.getBoundingClientRect();
            const pointerX = event.clientX - rect.left;
            const pointerY = event.clientY - rect.top;
            const scaleFactor = event.deltaY < 0 ? 1.08 : 0.92;
            const current = transformRef.current;
            const nextK = Math.min(4, Math.max(0.05, current.k * scaleFactor));
            const ratio = nextK / current.k;

            setTransform({
                k: nextK,
                x: pointerX - (pointerX - current.x) * ratio,
                y: pointerY - (pointerY - current.y) * ratio,
            });
        },
        [enabled, setTransform],
    );

    const onPointerDown = useCallback(
        (event: PointerEvent<HTMLElement>) => {
            if (!enabled || event.button !== 0) {
                return;
            }

            dragging.current = true;
            dragged.current = false;
            lastPoint.current = { x: event.clientX, y: event.clientY };
            event.currentTarget.setPointerCapture(event.pointerId);
        },
        [enabled],
    );

    const onPointerMove = useCallback(
        (event: PointerEvent<HTMLElement>) => {
            if (!enabled || !dragging.current) {
                return;
            }

            const dx = event.clientX - lastPoint.current.x;
            const dy = event.clientY - lastPoint.current.y;

            if (Math.hypot(dx, dy) > 3) {
                dragged.current = true;
            }

            lastPoint.current = { x: event.clientX, y: event.clientY };

            const current = transformRef.current;
            setTransform({
                ...current,
                x: current.x + dx,
                y: current.y + dy,
            });
        },
        [enabled, setTransform],
    );

    const onPointerUp = useCallback((event: PointerEvent<HTMLElement>) => {
        dragging.current = false;

        if (event.currentTarget.hasPointerCapture(event.pointerId)) {
            event.currentTarget.releasePointerCapture(event.pointerId);
        }
    }, []);

    return {
        transformRef,
        resetTransform,
        onWheel,
        onPointerDown,
        onPointerMove,
        onPointerUp,
        wasDragged: () => dragged.current,
        clearDragged: () => {
            dragged.current = false;
        },
        isDragging: () => dragging.current,
    };
}

export function getTransformRefValue(ref: RefObject<GraphTransform>): GraphTransform {
    return ref.current ?? { x: 0, y: 0, k: 1 };
}
