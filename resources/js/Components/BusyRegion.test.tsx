import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import BusyRegion from '@/Components/BusyRegion';

describe('BusyRegion', () => {
    it('shows page-style wait when busy and empty', () => {
        render(
            <BusyRegion busy empty label="Loading photos…">
                <p>Hidden content</p>
            </BusyRegion>,
        );

        expect(screen.getByText('Loading photos…')).toBeTruthy();
        expect(screen.queryByText('Hidden content')).toBeNull();
    });

    it('overlays children when busy with content', () => {
        render(
            <BusyRegion busy label="Updating…">
                <p>Visible rows</p>
            </BusyRegion>,
        );

        expect(screen.getByText('Visible rows')).toBeTruthy();
        expect(screen.getByText('Updating…')).toBeTruthy();
        expect(screen.getByText('Visible rows').parentElement?.getAttribute('aria-busy')).toBe('true');
    });

    it('renders children only when not busy', () => {
        render(
            <BusyRegion busy={false}>
                <p>Ready</p>
            </BusyRegion>,
        );

        expect(screen.getByText('Ready')).toBeTruthy();
        expect(screen.queryByRole('status')).toBeNull();
    });
});
