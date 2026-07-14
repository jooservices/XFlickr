# First run

After installation, configure credentials and connect accounts.

## 1. Open Connections

Navigate to **http://localhost:8082/connections**

## 2. Flickr → Apps → connect account

1. Create a [Flickr API application](https://www.flickr.com/services/apps/create/) if you do not have one.
2. Open **Flickr** → **Apps** and add your API key and secret.
3. Set callback URL to `{APP_URL}/flickr/callback` (e.g. `http://localhost:8082/flickr/callback`).
4. Click **Connect** and authorize your account.

## 3. Storage

Stay on **Connections**, switch to **Storage**, and choose at least one provider:

| Provider | Setup |
|---|---|
| Google Photos / Drive | Google Cloud OAuth client ID + secret under **Apps**, then connect under **Accounts** |
| OneDrive | Microsoft Azure app registration under **Apps**, then connect under **Accounts** |
| Cloudflare R2 | Endpoint, bucket, access key, secret key under **Accounts** |

## 4. Crawl your catalog

1. Go to **Connections** → **Flickr** → **Accounts** → select your account.
2. Click **Crawl** and choose types (contacts, photos, photosets, galleries, favorites).
3. Monitor progress on **Dashboard** or **Operations**.

## 5. Download and upload

When catalog data is indexed:

- **Download** — pull photo files to local server storage.
- **Upload** — push local files to your connected cloud storage.

## Related

- [Connections](../02-user-guide/connections.md)
- [Settings](../02-user-guide/settings.md) — runtime config
- [Dashboard](../02-user-guide/dashboard.md)
