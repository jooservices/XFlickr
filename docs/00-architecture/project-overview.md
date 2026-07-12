# Project overview

Visual architecture (flows, queues, OAuth, data stores): [Architecture diagrams](architecture-diagrams.md).

XFlickr is a self-hosted web application for managing Flickr accounts, crawling contacts and photo catalogs, downloading photos locally, and backing them up to cloud storage.

## Core purpose

Help users **own their Flickr data** by:

1. Connecting Flickr accounts via OAuth 1.0a
2. Indexing contacts and catalogs (photos, photosets, galleries, favorites) on demand
3. Downloading originals to server disk with deduplication
4. Uploading copies to Google Photos, Google Drive, OneDrive, or Cloudflare R2

## Design principles

- **Manual operations only** — crawls, downloads, and uploads are user-triggered; no background spidering
- **Flickr API quota respect** — rate limiting via `jooservices/xflickr-crawler` and Redis
- **Self-hosted** — user runs the full stack (MySQL, Redis, MongoDB, Horizon)
- **Deduplication** — skip already-downloaded or already-uploaded files

## Application layers

```
Browser (React 19 + Inertia)
    ↓
HTTP Request → Controller → FormRequest
    ↓
Services (business logic)
    ↓
Repositories (persistence queries)
    ↓
Models / crawler package tables
```

**Auth** (`Modules/Auth`): session login/logout, registration (inactive until `xflickr:auth:activate-user`), self-serve password reset (hashed token in DB + flashed URL; email not sent yet), and admin force-reset CLI. Layering: FormRequests → `AuthService` / `UserService` → repositories → `User`.

Background work runs through Laravel Horizon queues.

## Local URL

Development stack: **http://localhost:8082**
