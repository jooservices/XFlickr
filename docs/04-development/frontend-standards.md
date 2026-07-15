# Frontend standards

XFlickr uses React 19, Inertia 3, TypeScript, and Tailwind CSS 4.

## Structure

```text
resources/js/
├── Components/
│   ├── ui/           # Primitives (Button, MetricCard, …)
│   ├── layout/       # PageShell barrel over @jooservices/react-content
│   ├── form/         # Form macros
│   ├── Contacts/     # Contact list/detail + Graph/ (ContactGraphShell, toolbar, legend)
│   ├── Flickr/       # Account ops, crawl/expand action bars
│   ├── Catalog/      # Photo grid macro
│   ├── Transfer/     # Transfer batch panel
│   ├── Operations/   # Operations console panels
│   ├── Settings/     # Settings panels
│   └── Storage/      # Storage browse/reauthorize UI
├── Layouts/          # AppLayout — master chrome (JOO AppShell)
├── Pages/            # Inertia pages compose PageShell + domain components
├── hooks/            # usePolledResource, domain hooks
├── lib/              # apiClient, apiPaths, utilities
└── types.ts
```

## Layout

- **Master:** `Layouts/AppLayout` (JOO `AppShell` from `@jooservices/react-layout`). Centered top search via `AppShell.HeaderCenter` (command palette).
- **Content:** `PageShell*` from `@/Components/layout/page-shell` (`@jooservices/react-content`).
- Shared chrome from JOO v1.0.0 packages: `DataTable`, `Modal`, `Toaster`, action-buttons, `BaseCard`/`StatCard`, `ConfigPanel`. Keep domain macros (crawl bars, graph, ProviderCard, forms, BusyRegion).

## Component conventions

- Prefer JOO packages via thin `Components/ui` wrappers — not raw `<button>` / reinvented tables.
- Use `CrawlActionBar` for crawl/download/upload action groups.
- Prefer `PageShell` + `PageShellIdentity` / `PageShellCanvas` on authenticated pages (do not add new `PageHeading` usage). Primary CTAs only in Identity actions.
- Use `DataTable` for sortable tabular data; `Card` / `ProviderCard` / `MetricCard` for panels and stats.
- API calls: `apiGet`/`apiPost`/`apiPatch`/`apiDelete` against `/api/v1/*`.
- Poll live surfaces with `usePolledResource` (not raw `setInterval` in hooks/Pages — ESLint enforced).
- `useOperationsStream` is the documented exception: JSON polling only (no SSE on single-worker PHP) and folds snapshots into `activityHistory`.

See skill: `react-inertia-frontend` and rule `.cursor/rules/ui-buttons.mdc`.

## Inertia patterns

- Page props typed via `PageProps` and page-specific interfaces.
- Use `router.post()` for form actions with `preserveScroll: true` where appropriate.
- Poll JSON API endpoints for live data (Dashboard, Operations).

## Styling

- Tailwind utility classes; use `cn()` for conditional classes.
- Theme tokens: `--joo-*` in `resources/css/app.css` (demo-aligned slate / cyan primary).

## TypeScript

```bash
npm run typecheck
```

Required in CI. Fix type errors before opening PRs.

## Adding a new page

1. Create component in `resources/js/Pages/`
2. Add route in `routes/web.php` returning `Inertia::render()`
3. Add navigation link in `Layouts/AppLayout.tsx` if needed
4. Add user guide doc in `docs/02-user-guide/` if user-facing
