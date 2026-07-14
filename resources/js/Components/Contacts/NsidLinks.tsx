import ValueWithExternalLink from '@/Components/ui/ValueWithExternalLink';
import { flickrPeopleUrl } from '@/lib/catalog';
import { contactSubtext } from '@/lib/contact';
import { flickrContactPath } from '@/lib/flickrAccount';


export interface ContactNsidLinksProps {
    nsid: string;
    accountPublicId?: string | null;
    username?: string | null;
    realname?: string | null;
    subtext?: string | null;
    showSubtext?: boolean;
}

export default function ContactNsidLinks({
    nsid,
    accountPublicId,
    username,
    realname,
    subtext,
    showSubtext = true,
}: ContactNsidLinksProps) {
    const resolvedSubtext =
        subtext !== undefined
            ? subtext
            : showSubtext
              ? contactSubtext({ nsid, username, realname })
              : null;

    return (
        <ValueWithExternalLink
            value={nsid}
            href={accountPublicId ? flickrContactPath(accountPublicId, nsid) : undefined}
            externalHref={flickrPeopleUrl(nsid)}
            subtext={resolvedSubtext}
            mono
        />
    );
}
