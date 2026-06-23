# Frontend standards

XFlickr uses React 19, Inertia 3, TypeScript, and Tailwind CSS 4.

## Structure

```text
resources/js/
├── Components/     # Shared UI (Button, DataTable, CrawlActionBar, etc.)
├── Pages/          # Inertia pages (one per route)
├── Layouts/        # AppLayout with sidebar navigation
├── hooks/          # Reusable React hooks
├── lib/            # Utilities (cn, tableSort, api helpers)
└── types.ts        # Shared TypeScript types
```

## Component conventions

- Use shared `Button` and `ActionButton` — not raw `<button>` for actions.
- Use `CrawlActionBar` for crawl/download/upload action groups.
- Use `PageHeading` + `Breadcrumbs` on every page.
- Use `DataTable` for sortable tabular data.
- Use `Card` / `ProviderCard` for grouped settings panels.

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
