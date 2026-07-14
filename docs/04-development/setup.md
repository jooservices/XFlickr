# Development setup

## Quick start (Docker — operator)

```bash
cp .env.example .env
bash scripts/dev.sh up
```

The crawl engine lives in-repo as **`Modules/Crawler`** (`Modules\Crawler\*`). There is no sibling Packagist checkout or Docker bind-mount for the former `jooservices/xflickr-crawler` package.

See `bash scripts/dev.sh help` for `seed`, `quick`, `reload`, `refresh`, `down`, `logs`, and more.

## Quality commands (AI + CI)

| Command | Purpose |
|---|---|
| `bash scripts/test.sh gate` | Lint + PHPUnit + Vitest (before commit) |
| `bash scripts/test.sh gate:ci` | CI parity (before push) |
| `bash scripts/test.sh gate:lint` | Lint only |
| `./scripts/test-docker.sh --filter=Name` | Filtered PHPUnit |
| `composer instructions:verify` | Verify AI instruction sync |

## Local dev without Docker

See [Native installation](../01-getting-started/native-installation.md).

## Required reading

1. [`AGENTS.md`](../../AGENTS.md) — especially Docker policy
2. [Coding standards](coding-standards.md)
3. [Testing](testing.md)
4. [`ai/README.md`](../../ai/README.md)
5. [AI skills maintenance](ai-skills.md)

## IDE

- PHP: Laravel Pint for formatting (`vendor/bin/pint`)
- TypeScript: `npm run typecheck`

## Frontend dev server

Started automatically by `bash scripts/dev.sh up` (frontend container, port 5174).

Operator reload after UI changes:

```bash
bash scripts/dev.sh restart-frontend
```
