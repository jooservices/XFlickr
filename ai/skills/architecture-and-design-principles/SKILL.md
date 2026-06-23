---
name: architecture-and-design-principles
description: SOLID, thin controllers, YAGNI, and Laravel layering for XFlickr.
---

# Skill: architecture-and-design-principles

## Purpose

Guide structural decisions so changes respect XFlickr layering and package boundaries.

## When to use

New features, refactors, or when unsure where code belongs.

## Rules

- **SRP:** Controllers route; Services decide; Repositories query; Jobs delegate.
- **DIP:** Services depend on Repository abstractions, not Eloquent directly.
- **YAGNI:** No abstractions for one-time operations.
- **Package boundary:** Crawl logic stays in `jooservices/xflickr-crawler` — do not reimplement fetchers.
- **Manual crawl only:** Never add scheduled auto-spidering.
- Stop and ask when requirements conflict with existing architecture.

## Required reading

- `docs/00-architecture/application-standards.md`
- `docs/00-architecture/package-boundaries.md`
- `class-purpose-and-module-map`

## Related skills

- `form-request-service-repository`
- `crawler-pipeline-integrity`
