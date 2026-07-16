# Crawler module

Owns the XFlickr crawl engine, crawler catalog models, fetchers, rate limiting, and queue jobs. It is a DAG leaf: it must not import peer business modules.

See `docs/00-architecture/modules.md` and `ai/skills/crawler-pipeline-integrity/SKILL.md`.
