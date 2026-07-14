import { formatCount } from '@/lib/format';
import type { SpiderImpactEstimate } from '@/types';

type SpiderImpactSummaryProps = {
    impact: SpiderImpactEstimate;
    context: 'caps' | 'account';
};

export default function SpiderImpactSummary({ impact, context }: SpiderImpactSummaryProps) {
    return (
        <div className="space-y-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-950">
            <p className="font-medium text-amber-950">Estimated crawl load</p>
            <dl className="grid gap-2 sm:grid-cols-2">
                {impact.crawl_targets_known !== null && impact.contacts_known_capped !== null ? (
                    <div>
                        <dt className="text-xs text-amber-800/80">
                            {context === 'account' ? 'Known (saved contacts)' : 'Known from saved contacts'}
                        </dt>
                        <dd className="font-medium">
                            ~{formatCount(impact.crawl_targets_known)} targets (
                            {formatCount(impact.contacts_known_capped)} contacts ×{' '}
                            {impact.crawl_targets_per_contact} + seed)
                        </dd>
                    </div>
                ) : null}
                <div>
                    <dt className="text-xs text-amber-800/80">Ceiling (hard cap)</dt>
                    <dd className="font-medium">
                        up to ~{formatCount(impact.crawl_targets_ceiling)} targets (
                        {formatCount(impact.contacts_ceiling)} contacts)
                    </dd>
                </div>
                <div>
                    <dt className="text-xs text-amber-800/80">Per scheduler tick</dt>
                    <dd className="font-medium">
                        ~{formatCount(impact.crawl_targets_per_tick)} targets (
                        {formatCount(impact.max_new_contacts_per_run)} contacts)
                    </dd>
                </div>
                <div>
                    <dt className="text-xs text-amber-800/80">Max depth</dt>
                    <dd className="font-medium">{impact.max_depth}</dd>
                </div>
            </dl>
            <p className="text-xs text-amber-900/80">
                Each frontier contact queues Photos + Contacts crawls. Depth &gt; 0 discovery is unknown until runs
                expand — the ceiling is the hard stop. Estimates are crawl targets, not Horizon job rows.
            </p>
            {context === 'caps' ? (
                <p className="text-xs text-amber-900/80">
                    Enabling spider does not start work. Start Auto-expand on a Flickr account to begin.
                </p>
            ) : null}
        </div>
    );
}
