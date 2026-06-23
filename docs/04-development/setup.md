# Development setup

## Quick start (Docker)

```bash
cp .env.example .env
./scripts/docker-up.sh
```

## Quality commands

| Command | Purpose |
|---|---|
| `composer test:docker` | Full PHPUnit suite in isolated test stack |
| `./scripts/test-docker.sh --filter=Name` | Filtered tests |
| `npm run typecheck` | TypeScript check |
| `npm run build` | Production frontend build |
| `composer instructions:verify` | Verify AI instruction sync |

## Local dev without Docker

See [Native installation](../01-getting-started/native-installation.md).

## Required reading

1. [`AGENTS.md`](../../AGENTS.md) — especially Docker database safety
2. [Coding standards](coding-standards.md)
3. [Testing](testing.md)
4. [`ai/README.md`](../../ai/README.md)
5. [AI skills maintenance](ai-skills.md) — when editing skills or agent adapters

## IDE

- PHP: Laravel Pint for formatting (`vendor/bin/pint`)
- TypeScript: `npm run typecheck`

## Frontend dev server

```bash
docker compose up -d vite   # HMR on port 5174
# or
npm run dev
```
