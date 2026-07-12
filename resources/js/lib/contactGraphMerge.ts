import type { ContactGraphEdge, ContactGraphNode } from '@/types';

export function mergeContactGraphNodes(
    current: Map<string, ContactGraphNode>,
    incoming: ContactGraphNode[],
): Map<string, ContactGraphNode> {
    const next = new Map(current);

    for (const node of incoming) {
        const existing = next.get(node.nsid);
        next.set(node.nsid, existing ? { ...existing, ...node } : node);
    }

    return next;
}

export function mergeContactGraphEdges(
    current: ContactGraphEdge[],
    incoming: ContactGraphEdge[],
): ContactGraphEdge[] {
    const seen = new Set(current.map((edge) => `${edge.from}:${edge.to}`));
    const next = [...current];

    for (const edge of incoming) {
        const key = `${edge.from}:${edge.to}`;

        if (seen.has(key)) {
            continue;
        }

        seen.add(key);
        next.push(edge);
    }

    return next;
}
