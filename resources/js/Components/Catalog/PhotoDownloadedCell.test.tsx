import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import PhotoDownloadedCell from '@/Components/Catalog/PhotoDownloadedCell';

describe('PhotoDownloadedCell', () => {
    it('renders queued for pending', () => {
        render(<PhotoDownloadedCell status="pending" />);
        expect(screen.getByText('Queued')).toBeTruthy();
    });

    it('renders downloading spinner label', () => {
        render(<PhotoDownloadedCell status="downloading" />);
        expect(screen.getByText('Downloading…')).toBeTruthy();
    });

    it('renders view link when completed with url', () => {
        render(<PhotoDownloadedCell status="completed" viewUrl="/api/v1/stored-files/abc" />);
        const link = screen.getByRole('link', { name: 'View' });
        expect(link.getAttribute('href')).toBe('/api/v1/stored-files/abc');
    });

    it('renders dash for none', () => {
        render(<PhotoDownloadedCell status="none" />);
        expect(screen.getByText('—')).toBeTruthy();
    });
});
