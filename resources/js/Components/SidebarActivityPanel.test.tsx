import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import SidebarActivityPanel from '@/Components/SidebarActivityPanel';
import type { CrawlRun, FlickrAccount, TransferBatch } from '@/types';

const account: FlickrAccount = {
    public_id: 'acc-1',
    nsid: '123@N00',
    username: 'alice',
    fullname: 'Alice',
    app_profile: null,
    connected_at: null,
    is_active: true,
};

const accountByNsid = { '123@N00': account };

function downloadBatch(overrides: Partial<TransferBatch> = {}): TransferBatch {
    return {
        id: 12,
        type: 'download',
        connection_key: '123@N00',
        subject_nsid: '456@N01',
        group_type: 'owner',
        group_id: null,
        group_label: null,
        storage_account_id: null,
        status: 'running',
        total_count: 20,
        completed_count: 5,
        failed_count: 0,
        created_at: null,
        updated_at: null,
        ...overrides,
    };
}

function fetchRun(overrides: Partial<CrawlRun> = {}): CrawlRun {
    return {
        id: 7,
        connection_key: '123@N00',
        crawl_type: 'photos',
        subject_nsid: '456@N01',
        status: 'running',
        contacts_discovered: 0,
        photos_discovered: 3,
        api_calls: 10,
        started_at: null,
        completed_at: null,
        failed_reason: null,
        ...overrides,
    };
}

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

    it('hides finished snapshot rows and only shows running jobs', () => {
        const { container } = render(
            <SidebarActivityPanel
                fetchRuns={[
                    fetchRun({ id: 1, status: 'completed', photos_discovered: 9 }),
                    fetchRun({ id: 2, status: 'failed', photos_discovered: 1 }),
                ]}
                downloadBatches={[
                    downloadBatch({ id: 10, status: 'completed', completed_count: 20 }),
                    downloadBatch({ id: 11, status: 'completed_with_errors', completed_count: 18, failed_count: 2 }),
                    downloadBatch({ id: 12, status: 'failed', completed_count: 0, failed_count: 20 }),
                ]}
                uploadBatches={[
                    {
                        ...downloadBatch({ id: 30, type: 'upload', status: 'completed', completed_count: 4, total_count: 4 }),
                        type: 'upload',
                    },
                ]}
                loading={false}
                accountByNsid={accountByNsid}
            />,
        );

        expect(container.firstChild).toBeNull();
    });

    it('renders active download progress', () => {
        render(
            <SidebarActivityPanel
                fetchRuns={[fetchRun({ status: 'completed' })]}
                downloadBatches={[
                    downloadBatch({ status: 'completed', id: 9, completed_count: 20 }),
                    downloadBatch({ status: 'running', id: 12, completed_count: 5 }),
                ]}
                uploadBatches={[]}
                loading={false}
                accountByNsid={accountByNsid}
            />,
        );

        expect(screen.getByLabelText('Live operations')).toBeTruthy();
        expect(screen.getByText('Download')).toBeTruthy();
        expect(screen.getByText('5/20')).toBeTruthy();
        expect(screen.getByText('1')).toBeTruthy();
        expect(screen.queryByText('Fetch')).toBeNull();
        expect(screen.getByRole('link', { name: 'View Operations' })).toBeTruthy();
    });
});
