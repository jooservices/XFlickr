# Catalog

Browse photos, photosets, galleries, and favorites discovered by crawls.

## Pages

| Page | Path | Contents |
|---|---|---|
| Photos | `/photos` | All discovered photos |
| Photosets | `/photosets` | Photo sets / albums |
| Galleries | `/galleries` | Galleries |
| Favorites | `/favorites` | Favorited photos |

Account-scoped variants exist under `/flickr/accounts/{connection}/photos` etc.

## Filters

- **Owner NSID** — filter catalog to a specific contact or account owner
- **Search** — text search where supported
- **Sort** — column sorting with pagination

## Data source

Catalog rows live in crawler package tables (`xflickr_photos`, `xflickr_photosets`, etc.) populated by crawl operations.

Empty catalog? Run a crawl from [Flickr accounts](flickr-accounts.md) or [Contacts](contacts.md) first.

## Related

- [Operations](operations.md) — monitor crawl progress
