# Settings

Path: `/settings`

Configure runtime options, Flickr and storage credentials, and manage connections.

## Tabs

### General

- Global crawl pause toggle
- Runtime config entries (laravel-config backed)
- Default Flickr app profile selection

### Flickr

- Add/edit Flickr API app profiles (key, secret, callback URL)
- Connect and disconnect Flickr accounts
- View connected account list with connection status

### Storage

- Add/edit storage OAuth app profiles (Google, Microsoft)
- Configure Cloudflare R2 credentials
- Connect, disconnect, and set default storage accounts

## Global crawl pause

When enabled, a banner appears across the app and crawl jobs will not dispatch until resumed.

## Credential storage

| Type | Stored in |
|---|---|
| Flickr API keys | MongoDB (laravel-config) |
| Storage OAuth clients | MongoDB |
| Connected account tokens | MySQL (encrypted) |

## Related

- [First run](../01-getting-started/first-run.md)
