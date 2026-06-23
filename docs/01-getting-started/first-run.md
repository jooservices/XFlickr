# First run

After installation, configure credentials and connect accounts.

## 1. Open Settings

Navigate to **http://localhost:8082/settings**

## 2. Flickr tab

1. Create a [Flickr API application](https://www.flickr.com/services/apps/create/) if you do not have one.
2. Enter API key and secret in Settings → Flickr.
3. Set callback URL to `{APP_URL}/flickr/callback` (e.g. `http://localhost:8082/flickr/callback`).
4. Click **Connect Flickr** and authorize your account.

## 3. Storage tab

Choose at least one storage provider:

| Provider | Setup |
|---|---|
| Google Photos / Drive | Google Cloud OAuth client ID + secret |
| OneDrive | Microsoft Azure app registration |
| Cloudflare R2 | Endpoint, bucket, access key, secret key |

Connect a storage account via OAuth (or R2 key form).

## 4. Crawl your catalog

1. Go to **Flickr** → select your account.
2. Click **Crawl** and choose types (contacts, photos, photosets, galleries, favorites).
3. Monitor progress on **Dashboard** or **Operations**.

## 5. Download and upload

When catalog data is indexed:

- **Download** — pull photo files to local server storage.
- **Upload** — push local files to your connected cloud storage.

Both actions are manual and deduplicated (already-processed files are skipped).

## Troubleshooting

- OAuth callback errors: verify `APP_URL` and `FLICKR_CALLBACK_URL` match your browser URL.
- Empty catalog after crawl: check Operations page for failed runs or rate-limit cooldowns.
- See [Rate limiting](rate-limiting.md) and [Docker safety](../05-maintenance/docker-safety.md).
