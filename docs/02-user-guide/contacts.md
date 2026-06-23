# Contacts

Path: `/flickr/accounts/{connection}/contacts` (also linked from sidebar **Contacts**)

Browse Flickr contacts discovered by crawls and run per-contact operations.

## Contact table

Columns include NSID, display name, and catalog counts (photos, photosets, galleries). Counts link to filtered catalog views.

## Search and sort

Use the search field to filter by name or NSID. Click column headers to sort.

## Bulk actions

Select multiple contacts, then:

| Action | Description |
|---|---|
| **Crawl** | Index photos, photosets, galleries, or favorites for selected contacts |
| **Download** | Download photos for selected contacts |
| **Upload** | Upload locally stored photos for selected contacts |

## Per-contact detail

Click a contact row to open the detail view with catalog links and individual crawl actions.

## Notes

- Contacts appear after an account-level **contacts** crawl.
- Catalog counts are zero until that contact has been crawled.
- All operations use the discovering account's Flickr connection token.
