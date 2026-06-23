# Security Policy

## Reporting a Vulnerability

Do not open public issues for suspected vulnerabilities.

Report security concerns privately to [admin@jooservices.com](mailto:admin@jooservices.com) with:

- summary
- affected environment or version
- impact
- reproduction details
- logs or traces with secrets removed

No guaranteed SLA is promised.

## Do Not Share

Do not include secrets, API keys, OAuth tokens, cookies, session values, private URLs, `.env` contents, SSH keys, or production IPs in public issues, prompts, screenshots, or logs.

## XFlickr-Specific Risks

- Flickr OAuth token leakage in logs or exported data.
- Cloud storage credentials (Google, Microsoft, R2) stored in MongoDB via laravel-config — protect MongoDB access.
- Encrypted `token_payload` and `credentials` columns — protect database backups.
- Accidental exposure of downloaded Flickr photos on shared servers.
- Running tests or migrations against the local dev stack, wiping user data (see [Docker safety](docs/05-maintenance/docker-safety.md)).

## Dependency and Config Security

Run Composer and NPM audits where practical. Keep deploy secrets in environment variables or secret stores, not repository files. Review Horizon and crawl API logs before production because they may contain Flickr API response metadata.
