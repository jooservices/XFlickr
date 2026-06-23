# GitHub Copilot instructions — XFlickr

Read [`AGENTS.md`](../AGENTS.md) first. **Never touch local dev databases.**

## Project

Self-hosted Flickr archive manager — Laravel 12, React 19, Inertia 3.

## Non-negotiables

1. Tests only: `composer test:docker` — never `docker compose exec app php artisan test`
2. Backend flow: `Controller → FormRequest → Service → Repository`
3. Jobs delegate to Services only
4. Manual crawl only — no auto-spidering
5. Minimize scope; inspect source before inventing routes or commands

## Quality gates

```bash
composer test:docker
npm run typecheck
composer instructions:verify
```

## Skills

Canonical bodies: [`ai/skills/`](../ai/skills/). Start with `repo-quality-foundation` and domain skills for your task.

## Commits

Author/committer: `Viet Vu <jooservices@gmail.com>`. No AI co-authored trailers.
