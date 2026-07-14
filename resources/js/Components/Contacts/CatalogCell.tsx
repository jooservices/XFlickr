import OperationStatusCell from '@/Components/ui/OperationStatusCell';
import type { CrawlTypeState } from '@/types';

interface ContactCatalogCellProps {
    count: number;
    state?: CrawlTypeState;
}

export default function ContactCatalogCell({ count, state }: ContactCatalogCellProps) {
    if (!state?.processing) {
        return <span className="tabular-nums text-slate-600">{count}</span>;
    }

    const fetched = state.fetched ?? count;

    return (
        <OperationStatusCell completed={fetched} total={state.total ?? null} label="Fetching…" />
    );
}
