# GitHub Copilot instructions — XFlickr

Read [`AGENTS.md`](../AGENTS.md) first. **Never touch local dev stack.**

Test data: factories for every Eloquent model; Faker for incidental values; assert from created models (`docs/04-development/testing.md`).

## Project

Self-hosted Flickr archive manager — Laravel 12, React 19, Inertia 3.

Module scope / purpose / features: [`docs/00-architecture/modules.md`](../docs/00-architecture/modules.md). Routing skill: `class-purpose-and-module-map`.

## Non-negotiables

1. Tests only: `bash scripts/test.sh gate:test` — never `docker exec xflickr-dev-*` or `scripts/dev.sh`
2. Backend flow: `HTTP Request → Controller → FormRequest → Service → Repository → Model` (Commands → Service; dedicated FormRequest per HTTP action)
3. Jobs delegate to Services only
4. Manual crawl only — no auto-spidering
5. Minimize scope; inspect source before inventing routes or commands

## Quality gates

```bash
bash scripts/test.sh gate
bash scripts/test.sh gate:ci
composer instructions:verify
```

## Skills

Canonical bodies: [`ai/skills/`](../ai/skills/). Start with `repo-quality-foundation` and `operator-dev-docker`.

## Commits

Author/committer: `Viet Vu <jooservices@gmail.com>`. No AI co-authored trailers.
