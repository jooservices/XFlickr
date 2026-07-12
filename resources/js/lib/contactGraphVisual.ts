import type { ContactGraphNode } from '@/types';

export interface NodeVisualStyle {
    radius: number;
    fill: string;
    stroke: string;
    strokeWidth: number;
}

const ROOT_RADIUS = 10;
const MIN_CONTACT_RADIUS = 2.5;
const MAX_CONTACT_RADIUS = 8;

export function photoWeight(photosCount: number, maxPhotos: number): number {
    if (maxPhotos <= 0) {
        return 0;
    }

    const value = Math.log10(photosCount + 1) / Math.log10(maxPhotos + 1);

    return Math.min(1, Math.max(0, value));
}

export function maxPhotosCount(nodes: ContactGraphNode[]): number {
    let max = 0;

    for (const node of nodes) {
        max = Math.max(max, node.photos_count);
    }

    return max;
}

export function nodeVisualStyle(
    node: ContactGraphNode,
    maxPhotos: number,
    options: {
        selected: boolean;
        hovered: boolean;
        expanding: boolean;
        dimmed: boolean;
    },
): NodeVisualStyle {
    if (node.is_root) {
        return {
            radius: ROOT_RADIUS,
            fill: '#06b6d4',
            stroke: options.selected || options.hovered ? '#0891b2' : '#0e7490',
            strokeWidth: options.selected || options.hovered ? 2 : 1.2,
        };
    }

    const weight = photoWeight(node.photos_count, maxPhotos);
    const radius = MIN_CONTACT_RADIUS + weight * (MAX_CONTACT_RADIUS - MIN_CONTACT_RADIUS);

    if (options.expanding) {
        return {
            radius,
            fill: '#fbbf24',
            stroke: '#d97706',
            strokeWidth: options.selected || options.hovered ? 1.5 : 0.8,
        };
    }

    if (node.starred) {
        return {
            radius,
            fill: '#f59e0b',
            stroke: options.selected || options.hovered ? '#0891b2' : '#d97706',
            strokeWidth: options.selected || options.hovered ? 1.5 : 0.8,
        };
    }

    const lightness = Math.round(71 - weight * 43);
    const fill = `hsl(215 16% ${lightness}%)`;
    const stroke = options.selected || options.hovered ? '#0891b2' : `hsl(215 14% ${Math.max(35, lightness - 12)}%)`;

    return {
        radius,
        fill: options.dimmed ? `hsl(215 16% ${lightness}% / 0.35)` : fill,
        stroke,
        strokeWidth: options.selected || options.hovered ? 1.5 : 0.5 + weight * 0.8,
    };
}

export function contactHitRadius(radius: number): number {
    return Math.max(10, radius + 6);
}
