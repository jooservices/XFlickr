---
name: security-hardening
description: OAuth tokens, encrypted credentials, and secret handling for XFlickr.
---

# Skill: security-hardening

## Purpose

Prevent credential leakage and unsafe secret handling.

## Sensitive data

| Data | Location | Protection |
|---|---|---|
| Flickr API keys | MongoDB laravel-config | Never commit |
| Storage OAuth clients | MongoDB | Never commit |
| Flickr OAuth tokens | MySQL `flickr_accounts.token_payload` | Encrypted cast |
| Storage tokens | MySQL `storage_accounts.credentials` | Encrypted cast |
| R2 keys | MongoDB / Settings | Never commit |

## Rules

- Never log OAuth tokens or API secrets.
- Never commit `.env`, credentials JSON, or key files.
- Redact secrets from error reports and PR descriptions.
- Report vulnerabilities privately per `SECURITY.md`.

## XFlickr-specific risks

- Downloaded photos on disk — protect server filesystem access.
- MongoDB holds app credentials — restrict network access in production.
- Horizon dashboard — protect with middleware in production.

## Related skills

- `storage-driver-safety`
- `docker-dev-stack-safety`
