import {
    forceCollide,
    forceLink,
    forceManyBody,
    forceSimulation,
    type SimulationLinkDatum,
} from 'd3-force';

import type { GraphViewport } from '@/lib/contactGraphLayout';
import type { ContactGraphEdge, ContactGraphNode } from '@/types';


export interface SimulatedGraphNode extends ContactGraphNode {
    x: number;
    y: number;
    fx?: number | null;
    fy?: number | null;
}

export interface GraphBounds {
    minX: number;
    minY: number;
    maxX: number;
    maxY: number;
}

export interface ForceLayoutResult {
    nodes: SimulatedGraphNode[];
    bounds: GraphBounds;
}

interface ForceLink extends SimulationLinkDatum<SimulatedGraphNode> {
    source: string;
    target: string;
}

function simulationStrengths(nodeCount: number, incremental: boolean): {
    charge: number;
    linkDistance: number;
    collideRadius: number;
    ticks: number;
} {
    if (incremental) {
        return { charge: -6, linkDistance: 18, collideRadius: 3, ticks: 40 };
    }

    if (nodeCount > 800) {
        return { charge: -4, linkDistance: 14, collideRadius: 2.5, ticks: 80 };
    }

    if (nodeCount > 200) {
        return { charge: -10, linkDistance: 20, collideRadius: 3, ticks: 120 };
    }

    if (nodeCount > 50) {
        return { charge: -22, linkDistance: 28, collideRadius: 4, ticks: 160 };
    }

    return { charge: -45, linkDistance: 36, collideRadius: 5, ticks: 200 };
}

function computeBounds(nodes: SimulatedGraphNode[]): GraphBounds {
    if (nodes.length === 0) {
        return { minX: 0, minY: 0, maxX: 1, maxY: 1 };
    }

    let minX = nodes[0]?.x ?? 0;
    let minY = nodes[0]?.y ?? 0;
    let maxX = minX;
    let maxY = minY;

    for (const node of nodes) {
        minX = Math.min(minX, node.x);
        minY = Math.min(minY, node.y);
        maxX = Math.max(maxX, node.x);
        maxY = Math.max(maxY, node.y);
    }

    return { minX, minY, maxX, maxY };
}

export function simulateContactGraph(
    rootNsid: string,
    nodes: ContactGraphNode[],
    edges: ContactGraphEdge[],
    viewport: GraphViewport,
    previousPositions?: Map<string, { x: number; y: number }>,
): ForceLayoutResult {
    const centerX = viewport.width / 2;
    const centerY = viewport.height / 2;

    if (nodes.length === 0) {
        return { nodes: [], bounds: { minX: 0, minY: 0, maxX: viewport.width, maxY: viewport.height } };
    }

    const incremental = previousPositions !== undefined && previousPositions.size > 0;
    const { charge, linkDistance, collideRadius, ticks } = simulationStrengths(nodes.length, incremental);

    const simNodes: SimulatedGraphNode[] = nodes.map((node) => {
        const cached = previousPositions?.get(node.nsid);

        if (cached) {
            return {
                ...node,
                x: cached.x,
                y: cached.y,
            };
        }

        return {
            ...node,
            x: centerX + (Math.random() - 0.5) * Math.min(viewport.width, 120),
            y: centerY + (Math.random() - 0.5) * Math.min(viewport.height, 120),
        };
    });

    const nodeByNsid = new Map(simNodes.map((node) => [node.nsid, node]));

    const links: ForceLink[] = edges
        .filter((edge) => nodeByNsid.has(edge.from) && nodeByNsid.has(edge.to))
        .map((edge) => ({
            source: edge.from,
            target: edge.to,
        }));

    const root = nodeByNsid.get(rootNsid);
    if (root) {
        root.x = centerX;
        root.y = centerY;
        root.fx = centerX;
        root.fy = centerY;
    }

    const simulation = forceSimulation(simNodes)
        .force(
            'link',
            forceLink<SimulatedGraphNode, ForceLink>(links)
                .id((node) => node.nsid)
                .distance(linkDistance)
                .strength(0.5),
        )
        .force('charge', forceManyBody().strength(charge))
        .force('collide', forceCollide<SimulatedGraphNode>(collideRadius))
        .stop();

    for (let index = 0; index < ticks; index += 1) {
        simulation.tick();
    }

    if (root) {
        root.x = centerX;
        root.y = centerY;
    }

    return {
        nodes: simNodes,
        bounds: computeBounds(simNodes),
    };
}

export function initialZoomForBounds(bounds: GraphBounds, viewport: GraphViewport, padding = 48): number {
    const graphWidth = Math.max(bounds.maxX - bounds.minX, 1);
    const graphHeight = Math.max(bounds.maxY - bounds.minY, 1);
    const scaleX = (viewport.width - padding * 2) / graphWidth;
    const scaleY = (viewport.height - padding * 2) / graphHeight;

    return Math.min(1.2, Math.max(0.05, Math.min(scaleX, scaleY)));
}

export function initialPanForBounds(
    bounds: GraphBounds,
    viewport: GraphViewport,
    zoom: number,
): { x: number; y: number } {
    const graphCenterX = (bounds.minX + bounds.maxX) / 2;
    const graphCenterY = (bounds.minY + bounds.maxY) / 2;

    return {
        x: viewport.width / 2 - graphCenterX * zoom,
        y: viewport.height / 2 - graphCenterY * zoom,
    };
}

export function screenToGraph(
    clientX: number,
    clientY: number,
    canvasRect: DOMRect,
    transform: { x: number; y: number; k: number },
): { x: number; y: number } {
    const localX = clientX - canvasRect.left;
    const localY = clientY - canvasRect.top;

    return {
        x: (localX - transform.x) / transform.k,
        y: (localY - transform.y) / transform.k,
    };
}

export function findNearestNode(
    graphPoint: { x: number; y: number },
    nodes: SimulatedGraphNode[],
    getHitRadius: (node: SimulatedGraphNode) => number,
): SimulatedGraphNode | null {
    let nearest: SimulatedGraphNode | null = null;
    let nearestDistance = Number.POSITIVE_INFINITY;

    for (const node of nodes) {
        const hitRadius = getHitRadius(node);
        const dx = graphPoint.x - node.x;
        const dy = graphPoint.y - node.y;
        const distance = Math.hypot(dx, dy);

        if (distance <= hitRadius && distance < nearestDistance) {
            nearest = node;
            nearestDistance = distance;
        }
    }

    return nearest;
}
