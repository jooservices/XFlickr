---
name: code-style-and-conventions
description: PHP and TypeScript style conventions for XFlickr.
---

# Skill: code-style-and-conventions

## Purpose

Keep code style consistent across PHP and TypeScript.

## PHP

- `declare(strict_types=1);` on new files
- Run `vendor/bin/pint` before commit
- Typed properties and return types on public methods
- Group imports; no unused imports

## TypeScript / React

- Run `npm run typecheck` before PR
- Functional components with typed props
- Use `cn()` from `@/lib/cn` for conditional classes
- Import paths via `@/` alias

## Naming

- Controllers: `{Feature}Controller`
- FormRequests: `{Action}{Feature}Request`
- Services: `{Feature}Service` or `{Feature}{Verb}Service`
- Repositories: `{Model}Repository` or `{Domain}QueryRepository`
- Jobs: `{Verb}{Noun}Job`

## Related skills

- `react-inertia-frontend`
- `form-request-service-repository`
