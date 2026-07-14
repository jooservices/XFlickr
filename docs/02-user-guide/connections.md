# Connections

Path: `/connections`

Single hub for Flickr sources and storage destinations. Top nav label: **Connections**.

Tabs: **Flickr** | **Storage** only. Credentials and connected accounts live on the same page (no separate Accounts / Apps tabs).

Legacy URLs redirect here:

- `/flickr/accounts` → `/connections?provider=flickr`
- `/settings?tab=flickr` → `/connections?provider=flickr`
- `/settings?tab=storage` → `/connections?provider=storage`

## Flickr

### Connected accounts

Shows each connected Flickr account with:

- Display name and username
- Active status (one account is active at a time)
- API quota meter
- Connect / disconnect / reconnect
- Links to Contacts and Photos catalog

| Action | Description |
|---|---|
| **Crawl** | Index contacts, photos, photosets, galleries, or favorites for this account |
| **Download** | Queue download jobs for all pending photos owned by this account |
| **Upload** | Queue upload jobs for locally stored photos to default storage |

### API credentials

Same page lists Flickr app profiles (key, secret, callback). Use **Add credentials**, then **Connect** to authorize an account.

Credentials are stored in MongoDB (`xflickr_app.*`). Connected account tokens stay in MySQL.

## Storage

One list of destinations:

- Connected OAuth accounts (Google Photos, Drive, OneDrive) and Cloudflare R2
- Destinations with credentials saved but not yet authorized (“Ready to connect”)
- **Add destination** picks a provider, collects credentials (or R2 keys), then connects

Credentials are stored in MongoDB (`storage_app.*`) for OAuth apps; R2 keys are stored encrypted on the account. Connected tokens stay in MySQL.

## Related

- [Contacts](contacts.md)
- [Settings](settings.md) — runtime config only
- [Storage browse](storage-browse.md)
- [Rate limiting](rate-limiting.md)
