---
name: react-inertia-frontend
description: React 19, Inertia 3, and shared UI component patterns for XFlickr.
---

# Skill: react-inertia-frontend

## Purpose

Keep frontend pages consistent with XCrawlerII-aligned AppShell / PageShell patterns.

## Structure

- Pages: `resources/js/Pages/{Feature}/`
- Shared components: `resources/js/Components/` (`ui/`, `layout/`, plus PascalCase domain folders — `Contacts/Graph/`, `Flickr/`, `Catalog/`, `Transfer/`, …)
- Master layout: `resources/js/Layouts/AppLayout.tsx` → JOO `AppShell`
- Content layout: `resources/js/Components/layout/page-shell.tsx` → JOO `PageShell*`
- Types: `resources/js/types.ts`
- API paths: `resources/js/lib/apiPaths.ts` (`API_V1 = '/api/v1'`)

## Component rules

- Authenticated pages: `AppLayout` → `PageShell` → Identity / ControlBar / Canvas (not standalone `PageHeading`)
- Prefer JOO packages (`@jooservices/react-*` v1.0.0) via thin `Components/ui` wrappers — do not reinvent DataTable / Modal / Toaster / Button / Card
- Use `Button` / `ActionButton` — not raw `<button>` for actions (except domain chrome that must match layout search tokens)
- Use shared `Modal` (`Modal.Header` / `Modal.Body` / `Modal.Footer` from `@jooservices/react-modal`) for dialogs
- Use crawl/expand bars (`Flickr/CrawlActionBar`, `Flickr/ExpandActionBar`) for crawl action groups
- Use JOO `DataTable` (via `@/Components/ui/DataTable` adapter) for sortable tables; `MetricCard` → JOO `StatCard`
- Keep local: forms (`Input`, `SearchField`, `FilterBar`), `BusyRegion`, `EmptyState`, `ProviderCard`, graph/charts/transfer macros, `BulkActionBar`
- Primary page CTAs live in `PageShellIdentity.actions` only
- AppShell top search (“Jump to”) sits in `AppShell.HeaderCenter` (centered)
- Use `ProviderCard` for settings connection cards
- Poll with `usePolledResource` against `/api/v1/*` (ESLint bans raw `setInterval` in hooks/Pages; UI countdowns use `useCountdown`)
- `useOperationsStream`: WebSocket via Laravel Echo/Reverb when `VITE_REVERB_APP_KEY` is set (private `operations` channel); bootstrap + reconnect via `/api/v1/operations/snapshot`; poll fallback after 15s disconnect. Shared via `OperationsStreamProvider` in `AppLayout`. SSE `/operations/stream` is frozen (one-shot legacy).
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
- JOO packages ship `styles.css` + `--joo-*` tokens (see `resources/css/app.css`); do not invent a parallel design system
- Semantic color: MetricCard tones and StatusBadge — see `.cursor/rules/ui-buttons.mdc` (neutral=`slate`, active=`cyan`, healthy=`emerald`, caution=`amber`, problem=`rose`; Delete=`destructive`, recovery actions=`secondary`)
- Native `<select>`: always set explicit `text-slate-*` with `bg-white`; avoid unpaired `dark:bg-*` without `dark:text-*` (app is light-first via `color-scheme: light`)
- Paginated tables: pass `meta` + `onPageChange` to `DataTable` — do not use the standalone `Pagination` footer for list tables
- Bulk row actions: `useTableSelection` + DataTable `selection` / `bulkActions` / `onBulkClear` (see Contacts, Catalog Photos, Storage Browse)
## Before adding UI

Check existing Components and domain folders first. Prefer composing PageShell + domain components over new page chrome.

## Related skills

- `api-response-standards`
- `class-purpose-and-module-map`
