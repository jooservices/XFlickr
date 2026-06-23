export interface ContactSubtextInput {
    nsid: string;
    username?: string | null;
    realname?: string | null;
}

export function contactSubtext({ nsid, username, realname }: ContactSubtextInput): string {
    const name = realname?.trim() || username?.trim() || '';
    const handle = username?.trim() || nsid;

    return name ? `${name} · @${handle}` : `@${handle}`;
}
