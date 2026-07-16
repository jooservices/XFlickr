import { Head, Link } from '@inertiajs/react';

import CrawlActionBar from '@/Components/Flickr/CrawlActionBar';
import ExpandActionBar from '@/Components/Flickr/ExpandActionBar';
import { PageShell, PageShellCanvas, PageShellIdentity } from '@/Components/layout/page-shell';
import Card from '@/Components/ui/Card';
import AppLayout from '@/Layouts/AppLayout';
import { accountLabel, flickrAccountPageCrumbs } from '@/lib/breadcrumbs';
import { crawlSubjectForAccount } from '@/lib/crawlSubject';
import { flickrAccountPath } from '@/lib/flickrAccount';
import { shortPublicId } from '@/lib/publicId';
import type { FlickrAccount, PageProps } from '@/types';

interface Props extends PageProps {
    account: FlickrAccount;
}

const QUICK_LINKS = [
    { label: 'Contacts', suffix: '/contacts' },
    { label: 'Photos', suffix: '/photos' },
    { label: 'Photosets', suffix: '/photosets' },
    { label: 'Galleries', suffix: '/galleries' },
] as const;

export default function FlickrShow({ account }: Props) {
    const displayName = accountLabel(account);

    return (
        <AppLayout>
            <Head title={displayName} />

            <PageShell>
                <PageShellIdentity
                    breadcrumbs={flickrAccountPageCrumbs(account, { linkAccount: false })}
                    title={displayName}
                    subtitle={`${account.fullname ?? account.nsid} · ${shortPublicId(account.public_id)}`}
                    actions={
                        <>
                            <CrawlActionBar
                                scope="account"
                                accountPublicId={account.public_id}
                                subjectLabel={crawlSubjectForAccount(account)}
                            />
                            <ExpandActionBar accountPublicId={account.public_id} />
                        </>
                    }
                />

                <PageShellCanvas className="space-y-6" variant="plain">

                <Card
                    title="Account overview"
                    subtitle="Stats and activity summary will appear here."
                    showFooter={false}
                >
                    <p className="text-sm text-slate-600">
                        Detailed crawl, catalog, and transfer statistics for this account are coming soon.
                    </p>
                </Card>

                <div className="flex flex-wrap gap-x-4 gap-y-2 text-sm">
                    {QUICK_LINKS.map((link) => (
                        <Link
                            key={link.suffix}
                            href={flickrAccountPath(account.public_id, link.suffix)}
                            className="font-medium text-cyan-700 hover:underline"
                        >
                            {link.label}
                        </Link>
                    ))}
                </div>
                </PageShellCanvas>
            </PageShell>
        </AppLayout>
    );
}
