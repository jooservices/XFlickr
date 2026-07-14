---
name: react-inertia-frontend
description: React 19, Inertia 3, and shared UI component patterns for XFlickr.
---

# Skill: react-inertia-frontend

## Purpose

Keep frontend pages consistent with XCrawlerII-aligned AppShell / PageShell patterns.

## Structure

- Pages: `resources/js/Pages/{Feature}/`
- Shared components: `resources/js/Components/` (`ui/`, `macros/`, `layout/`)
- Master layout: `resources/js/Layouts/AppLayout.tsx` → JOO `AppShell`
- Content layout: `resources/js/Components/layout/page-shell.tsx` → JOO `PageShell*`
- Types: `resources/js/types.ts`
- API paths: `resources/js/lib/apiPaths.ts` (`API_V1 = '/api/v1'`)

## Component rules

- Authenticated pages: `AppLayout` → `PageShell` → Identity / ControlBar / Canvas (not standalone `PageHeading`)
- Use `Button` / `ActionButton` — not raw `<button>` for actions
- Use shared `Modal` (`Modal.Header` / `Modal.Body` / `Modal.Footer`) for dialogs — portals to `document.body`
- Use crawl/expand macros (`CrawlActionBar`, `ExpandActionBar`) for action groups
- Use JOO / local `DataTable` for sortable tables; `MetricCard` for stats
- Use `ProviderCard` for settings connection cards
- Poll with `usePolledResource` against `/api/v1/*`
- **API wait affordances** — always show a wait indicator scoped to what is blocked:
  - `PageLoading` / `BusyRegion` with `empty` — primary canvas content not ready yet
  - `BusyRegion` overlay / `DataTable` `busy` — panel/table refetch while content exists
  - `LoadingIndicator` — inline control/group waits (search, load-more, modal preview)
  - Do not use a global app spinner for every `apiGet`

## Inertia patterns

- Typed page props extending `PageProps`
- `router.post()` with `preserveScroll: true` for actions
- JSON mutations via `apiClient` (`apiGet` / `apiPost` / `apiPatch` / `apiDelete`)

## Styling

- Tailwind utilities; `cn()` for conditional classes
- Match existing slate neutrals; avoid inventing a parallel design system

## Before adding UI

Check existing Components and macros first. Prefer composing PageShell + macros over new page chrome.

## Related skills

- `api-response-standards`
- `class-purpose-and-module-map`
