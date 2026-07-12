import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import SidebarActivityPanel from '@/Components/SidebarActivityPanel';

describe('SidebarActivityPanel', () => {
    it('hides when there are no active jobs', () => {
        const { container } = render(
            <SidebarActivityPanel
                fetchRuns={[]}
                downloadBatches={[]}
                uploadBatches={[]}
                loading={false}
                accountByNsid={{}}
            />,
        );

        expect(container.firstChild).toBeNull();
    });

    it('renders active download progress', () => {
        render(
            <SidebarActivityPanel
                fetchRuns={[]}
                downloadBatches={[
                    {
                        id: 12,
                        type: 'download',
                        connection_key: '123@N00',
                        subject_nsid: '456@N01',
                        group_type: 'owner',
                        group_id: null,
                        group_label: null,
                        storage_account_id: null,
                        status: 'processing',
                        total_count: 20,
                        completed_count: 5,
                        failed_count: 0,
                        created_at: null,
                        updated_at: null,
                    },
                ]}
                uploadBatches={[]}
                loading={false}
                accountByNsid={{
                    '123@N00': {
                        public_id: 'acc-1',
                        nsid: '123@N00',
                        username: 'alice',
                        fullname: 'Alice',
                        app_profile: null,
                        connected_at: null,
                        is_active: true,
                    },
                }}
            />,
        );

        expect(screen.getByLabelText('Live operations')).toBeTruthy();
        expect(screen.getByText('Download')).toBeTruthy();
        expect(screen.getByText('5/20')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'View Operations' })).toBeTruthy();
    });
});
