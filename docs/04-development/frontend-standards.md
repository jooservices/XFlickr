# Frontend standards

XFlickr uses React 19, Inertia 3, TypeScript, and Tailwind CSS 4.

## Structure

```text
resources/js/
├── Components/
│   ├── ui/           # Primitives (Button, MetricCard, …)
│   ├── layout/       # PageShell barrel over @jooservices/react-content
│   ├── form/         # Form macros
│   └── macros/       # Domain composites (ContactGraphShell, CrawlActionBar, …)
├── Layouts/          # AppLayout — master chrome (JOO AppShell)
├── Pages/            # Inertia pages compose PageShell + macros
├── hooks/            # usePolledResource, domain hooks
├── lib/              # apiClient, apiPaths, utilities
└── types.ts
```

## Layout

- **Master:** `Layouts/AppLayout` (JOO `AppShell` from `@jooservices/react-layout`).
- **Content:** `PageShell*` from `@/Components/layout/page-shell` (`@jooservices/react-content`).
- Shared: JOO `DataTable`, local cards/stats (`MetricCard`), `Toaster`.

## Component conventions

- Use shared `Button` and `ActionButton` — not raw `<button>` for actions.
- Use `CrawlActionBar` for crawl/download/upload action groups.
- Prefer `PageShell` + `PageShellIdentity` / `PageShellCanvas` on authenticated pages (do not add new `PageHeading` usage).
- Use `DataTable` for sortable tabular data.
- Use `Card` / `ProviderCard` / `MetricCard` for panels and stats.
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
- Color palette: slate neutrals, cyan accents for links/active states.

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
