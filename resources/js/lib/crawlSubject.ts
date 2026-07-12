export interface CrawlSubjectLabel {
    title: string;
    nsid: string;
}

export function crawlSubjectForAccount(account: {
    username: string | null;
    fullname?: string | null;
    nsid: string;
}): CrawlSubjectLabel {
    return {
        title: account.username ?? account.fullname ?? account.nsid,
        nsid: account.nsid,
    };
}

export function crawlSubjectForContact(contact: {
    username: string | null;
    realname?: string | null;
    nsid: string;
}): CrawlSubjectLabel {
    return {
        title: contact.username ?? contact.realname ?? contact.nsid,
        nsid: contact.nsid,
    };
}

export function crawlSubjectForPhoto(photo: {
    flickr_photo_id: string;
    owner_nsid?: string | null;
    title?: string | null;
}): CrawlSubjectLabel {
    const trimmedTitle = photo.title?.trim();

    return {
        title: trimmedTitle !== undefined && trimmedTitle !== '' ? trimmedTitle : photo.flickr_photo_id,
        nsid: photo.owner_nsid ?? photo.flickr_photo_id,
    };
}
