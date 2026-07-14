import { describe, expect, it } from 'vitest';

import type { ContactGraphEdge, ContactGraphNode } from '@/types';

import { mergeContactGraphEdges, mergeContactGraphNodes } from './contactGraphMerge';

function node(partial: Partial<ContactGraphNode> & Pick<ContactGraphNode, 'nsid'>): ContactGraphNode {
    return {
        label: partial.nsid,
        username: null,
        realname: null,
        is_root: false,
        starred: false,
        note_preview: null,
        child_count: 0,
        photos_count: 0,
        ...partial,
    };
}

describe('mergeContactGraphNodes', () => {
    it('merges by nsid and overwrites fields', () => {
        const current = new Map<string, ContactGraphNode>([
            ['1@N01', node({ nsid: '1@N01', username: 'old' })],
        ]);
        const next = mergeContactGraphNodes(current, [
            node({ nsid: '1@N01', username: 'new' }),
            node({ nsid: '2@N01', username: 'other' }),
        ]);

        expect(next.get('1@N01')?.username).toBe('new');
        expect(next.size).toBe(2);
    });
});

describe('mergeContactGraphEdges', () => {
    it('deduplicates directed edges', () => {
        const current: ContactGraphEdge[] = [{ id: 1, from: 'a', to: 'b' }];
        const merged = mergeContactGraphEdges(current, [
            { id: 1, from: 'a', to: 'b' },
            { id: 2, from: 'b', to: 'c' },
        ]);

        expect(merged).toHaveLength(2);
    });
});
