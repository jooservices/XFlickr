import { useCallback, useEffect, useRef } from 'react';

import {
    findNearestNode,
    screenToGraph,
    type SimulatedGraphNode,
} from '@/lib/contactGraphForceLayout';
import {
    contactHitRadius,
    maxPhotosCount,
    nodeVisualStyle,
    photoWeight,
} from '@/lib/contactGraphVisual';
import type { ContactGraphEdge } from '@/types';

import type { GraphTransform } from './useGraphPanZoom';

interface DrawGraphOptions {
    ctx: CanvasRenderingContext2D;
    width: number;
    height: number;
    transform: GraphTransform;
    nodes: SimulatedGraphNode[];
    edges: ContactGraphEdge[];
    selectedNsid: string | null;
    hoveredNsid: string | null;
    expandingNsids: Set<string>;
    highlightedNsids: Set<string>;
}

function buildHighlightedNsids(
    edges: ContactGraphEdge[],
    activeNsid: string | null,
): Set<string> {
    if (!activeNsid) {
        return new Set();
    }

    const related = new Set<string>([activeNsid]);

    for (const edge of edges) {
        if (edge.from === activeNsid) {
            related.add(edge.to);
        }

        if (edge.to === activeNsid) {
            related.add(edge.from);
        }
    }

    return related;
}

export function drawContactGraph({
    ctx,
    width,
    height,
    transform,
    nodes,
    edges,
    selectedNsid,
    hoveredNsid,
    expandingNsids,
    highlightedNsids,
}: DrawGraphOptions): void {
    ctx.clearRect(0, 0, width, height);
    ctx.fillStyle = '#f1f5f9';
    ctx.fillRect(0, 0, width, height);

    ctx.save();
    ctx.translate(transform.x, transform.y);
    ctx.scale(transform.k, transform.k);

    const maxPhotos = maxPhotosCount(nodes);
    const positionedByNsid = new Map(nodes.map((node) => [node.nsid, node]));
    const dimEdges = highlightedNsids.size > 0;

    for (const edge of edges) {
        const from = positionedByNsid.get(edge.from);
        const to = positionedByNsid.get(edge.to);

        if (!from || !to) {
            continue;
        }

        const isHighlighted = highlightedNsids.has(edge.from) && highlightedNsids.has(edge.to);

        ctx.beginPath();
        ctx.moveTo(from.x, from.y);
        ctx.lineTo(to.x, to.y);
        ctx.strokeStyle = isHighlighted ? '#0891b2' : '#cbd5e1';
        ctx.lineWidth = (isHighlighted ? 1.2 : 0.6) / transform.k;
        ctx.globalAlpha = dimEdges && !isHighlighted ? 0.2 : isHighlighted ? 0.9 : 0.45;
        ctx.stroke();
    }

    ctx.globalAlpha = 1;

    for (const node of nodes) {
        const isSelected = selectedNsid === node.nsid;
        const isHovered = hoveredNsid === node.nsid;
        const style = nodeVisualStyle(node, maxPhotos, {
            selected: isSelected,
            hovered: isHovered,
            expanding: expandingNsids.has(node.nsid),
            dimmed: highlightedNsids.size > 0 && !highlightedNsids.has(node.nsid),
        });

        ctx.beginPath();
        ctx.arc(node.x, node.y, style.radius, 0, Math.PI * 2);
        ctx.fillStyle = style.fill;
        ctx.fill();
        ctx.strokeStyle = style.stroke;
        ctx.lineWidth = style.strokeWidth / transform.k;
        ctx.stroke();

        if (node.is_root) {
            ctx.font = `${10 / transform.k}px system-ui, sans-serif`;
            ctx.fillStyle = '#155e75';
            ctx.textAlign = 'center';
            ctx.fillText('Me', node.x, node.y + style.radius + 12 / transform.k);
        }
    }

    ctx.restore();
}

export function useContactGraphCanvas() {
    const canvasRef = useRef<HTMLCanvasElement | null>(null);
    const rafRef = useRef<number | null>(null);

    const scheduleDraw = useCallback((draw: () => void) => {
        if (rafRef.current !== null) {
            cancelAnimationFrame(rafRef.current);
        }

        rafRef.current = requestAnimationFrame(() => {
            rafRef.current = null;
            draw();
        });
    }, []);

    useEffect(
        () => () => {
            if (rafRef.current !== null) {
                cancelAnimationFrame(rafRef.current);
            }
        },
        [],
    );

    const hitTest = useCallback(
        (
            clientX: number,
            clientY: number,
            nodes: SimulatedGraphNode[],
            transform: GraphTransform,
        ): SimulatedGraphNode | null => {
            const canvas = canvasRef.current;

            if (!canvas) {
                return null;
            }

            const rect = canvas.getBoundingClientRect();
            const graphPoint = screenToGraph(clientX, clientY, rect, transform);
            const maxPhotos = maxPhotosCount(nodes);

            return findNearestNode(graphPoint, nodes, (node) =>
                contactHitRadius(
                    node.is_root
                        ? 10
                        : 2.5 + photoWeight(node.photos_count, maxPhotos) * (8 - 2.5),
                ),
            );
        },
        [],
    );

    return {
        canvasRef,
        scheduleDraw,
        hitTest,
        buildHighlightedNsids,
    };
}
