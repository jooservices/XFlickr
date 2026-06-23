---
name: react-inertia-frontend
description: React 19, Inertia 3, and shared UI component patterns for XFlickr.
---

# Skill: react-inertia-frontend

## Purpose

Keep frontend pages consistent with existing XFlickr UI patterns.

## Structure

- Pages: `resources/js/Pages/{Feature}/`
- Shared components: `resources/js/Components/`
- Layout: `resources/js/Layouts/AppLayout.tsx`
- Types: `resources/js/types.ts`

## Component rules

- Use `Button` / `ActionButton` — not raw `<button>` for actions
- Use `CrawlActionBar` for crawl/download/upload action groups
- Use `PageHeading` + `Breadcrumbs` on every page
- Use `DataTable` for sortable tables
- Use `Card` / `ProviderCard` for settings panels
- Use `RateLimitMeter` for API quota display

## Inertia patterns

- Typed page props extending `PageProps`
- `router.post()` with `preserveScroll: true` for actions
- Poll `/api/*` endpoints for live data (Dashboard, Operations)

## Styling

- Tailwind utilities; `cn()` for conditional classes
- Slate neutrals, cyan accents for links/active nav

## Before adding UI

1. Check if a shared component already exists
2. Match analogous pages (Contacts, Catalog, Flickr Index)
3. Run `npm run typecheck`

## Related skills

- `docs/04-development/frontend-standards.md`
- `.cursor/rules/ui-buttons.mdc` (thin pointer)
