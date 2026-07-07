import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import StatusBadge from '@/Components/StatusBadge';

describe('StatusBadge', () => {
    it('renders status label', () => {
        render(<StatusBadge status="running" />);
        expect(screen.getByText('running')).toBeTruthy();
    });
});
