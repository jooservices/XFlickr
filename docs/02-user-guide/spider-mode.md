# Spider mode

Automatic breadth-first expansion of Flickr contacts (opt-in). Spider discovers contacts from your connected account, crawls each contact's photos, and enqueues newly discovered **public** contacts up to configured depth and caps.

## Enable

- Spider is **disabled by default**.
- Open **Settings → General** and edit the **Spider** runtime config group (stored in MongoDB via `jooservices/laravel-config`).
- Set **`spider.enabled`** to `true` before using Start on the Operations page.

## Runtime config keys

| Path | Default | Purpose |
|------|---------|---------|
| `spider.enabled` | `false` | Master switch |
| `spider.max_depth` | `2` | Maximum contact depth from seed |
| `spider.max_new_contacts_per_run` | `25` | Contacts queued per scheduler tick |
| `spider.max_contacts_total` | `500` | Cap per spider run |

Values are curated core config: edit in Settings, reset to default, or override in MongoDB. They are **not** `.env` variables.

## Behaviour

1. **Start** (Operations page) — Creates a spider run and queues an owner contacts crawl. The frontier is seeded when that crawl completes (`ContactsCrawlCompleted` event).
2. **Expand** — Scheduler runs `xflickr:spider:expand` when `spider.enabled` is true. For each pending frontier contact, queues photo crawl plus subject contacts crawl (public list).
3. **Discover** — When a subject contacts crawl completes, newly discovered NSIDs are enqueued at depth + 1 until `spider.max_depth` or caps are hit.
4. **Stop** — Pauses the active run; frontier state is retained.

Global crawl pause (`xflickr.global_pause` in the same Settings panel) stops expansion while enabled.

## Depth > 0 limitation

Flickr only exposes another user's **public** contact list via `flickr.contacts.getPublicList`. Spider depth > 0 therefore discovers public contacts of each frontier contact, not private friend lists.

## Package dependency

Spider BFS requires **`jooservices/xflickr-crawler` ^1.3.0** (subject-scoped contacts crawl, `xflickr_subject_contacts`, `ContactsCrawlCompleted` event). See [xflickr-crawler spider extensions](../04-development/xflickr-crawler-spider-extensions.md).

## Safety

- Respects Flickr API rate limits and global pause.
- Hard caps prevent runaway contact growth.
- Manual per-contact crawls remain available and unchanged.
