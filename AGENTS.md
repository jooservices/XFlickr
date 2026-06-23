# AGENTS.md — XFlickr

Read this file before running commands or changing code in this repository.

## Must-read first

1. This file (`AGENTS.md`)
2. [Documentation hub](docs/README.md)
3. [AI skills map](ai/README.md)
4. [Docker safety](docs/05-maintenance/docker-safety.md)

---

## ABSOLUTE RULE — local Docker databases (NO EXCEPTIONS)

**AI agents MUST NOT cause any database change on the local dev stack (`docker-compose.yml`).**

- **No exceptions.** Not for tests. Not for migrations. Not for seeds. Not for debugging. Not for "just verifying". Not for "it should be safe".
- Applies to **MySQL** (`xflickr`) **and** **MongoDB** (`xflickr`).
- Applies to **`docker compose exec`** on any local service (`app`, `mysql`, `mongodb`, `horizon`, `scheduler`).

The only stack agents may use for **any** database work is **`docker-compose.test.yml`** (isolated test stack).

### FORBIDDEN on local dev stack (agents — zero exceptions)

Never run via `docker compose` without `-f docker-compose.test.yml`:

| Category | Examples |
|---|---|
| Tests | `php artisan test`, `composer test`, PHPUnit |
| MySQL destructive | `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, `db:seed` |
| MySQL writes | `php artisan migrate`, `php artisan db:*`, tinker with DB writes |
| MongoDB | `mongosh`, drop database, any write to `xflickr` |
| Config / events stores | `config-store:*`, `events:*` that write indexes or data |
| Direct DB clients | `mysql` CLI, `mongosh`, redis FLUSHALL on dev |

`docker compose exec` **bypasses** `docker/entrypoint.sh`. **There is no runtime safety net.**

### PERMITTED on local dev stack (agents only)

- `docker compose up`, `down`, `build`, `restart`, `logs`, `ps`, `pull`
- `docker compose exec app composer install` (no DB)
- `docker compose exec app npm ci`, `npm run build` (no DB)

### USER-ONLY maintenance scripts

If the user **explicitly** requests a reset, run **only** the named script:

```bash
./scripts/reset-local-mongodb.sh   # user asked to reset MongoDB
```

### REQUIRED for tests (only path)

```bash
composer test:docker
./scripts/test-docker.sh --filter=ExampleTest
docker compose -f docker-compose.test.yml run --rm test php artisan test
```

Test stack: sqlite `:memory:`, `MONGODB_DATABASE=xflickr_test` — never dev volumes.

Skills: `xflickr-docker-testing`, `docker-dev-stack-safety`

---

## Project identity

**XFlickr** — self-hosted Flickr archive manager (Laravel 12 + React 19 + Inertia 3).

- Crawling: `jooservices/xflickr-crawler`
- App credentials: **MongoDB** via laravel-config (`xflickr_app.*`, `storage_app.*`)
- Connected accounts: **MySQL** (`flickr_accounts`, `storage_accounts`)
- Local URL: **http://localhost:8082**

## Architecture boundaries

Mandatory backend flow:

```
Controller → FormRequest → Service → Repository → Model
```

Jobs delegate business logic to Services only.

| Layer | Owns |
|---|---|
| XFlickr app | OAuth, downloads, uploads, storage browse, transfer tracking, Settings UI |
| `jooservices/xflickr-crawler` | Crawl runs, fetchers, rate limits, catalog tables |

See [Application standards](docs/00-architecture/application-standards.md) and [Package boundaries](docs/00-architecture/package-boundaries.md).

## Non-negotiables

1. **Never touch local dev databases** — see absolute rule above.
2. **Tests only in test stack** — `composer test:docker`.
3. **Manual crawl only** — do not add auto-spidering without explicit product decision.
4. **Inspect source first** — do not invent routes, commands, or behavior.
5. **Minimize scope** — focused diffs; match existing conventions.

## Quality gates

Before opening a PR:

```bash
composer test:docker
npm run typecheck          # when frontend changed
composer instructions:verify
```

**Never:**

```bash
docker compose exec app php artisan test
```

## Skill routing

| Task type | Read first |
|---|---|
| Any change | `repo-quality-foundation` |
| Docker / DB commands | `xflickr-docker-testing`, `docker-dev-stack-safety` |
| Crawl features | `crawler-pipeline-integrity` |
| Download / upload | `transfer-pipeline-safety` |
| Storage OAuth / R2 | `storage-driver-safety` |
| Migrations | `database-migration-safety` |
| Frontend pages | `react-inertia-frontend` |
| API endpoints | `api-response-standards` |
| New backend code | `form-request-service-repository` |
| Non-trivial features | `multi-llm-plan-review` |
| Before PR | `review-and-risk-assessment`, `documentation-sync` |

Full index: [ai/README.md](ai/README.md)

## Git / commit policy

- All commits: `Viet Vu <jooservices@gmail.com>` as author and committer.
- Never commit as Cursor Agent or other AI identities.
- Do not add `Co-authored-by` trailers for AI tools.
- Only create commits when the user explicitly requests.

## Change checklist

- [ ] Inspected affected source files
- [ ] Tests pass: `composer test:docker`
- [ ] Typecheck passes if frontend changed: `npm run typecheck`
- [ ] Docs updated for user-visible changes
- [ ] `CHANGELOG.md` `[Unreleased]` updated if applicable
- [ ] `composer instructions:verify` passes

## Tool adapter map

| Tool | Entry |
|---|---|
| **All agents** | `AGENTS.md` (this file) |
| **Claude Code** | [CLAUDE.md](CLAUDE.md) |
| **Cursor** | `.cursor/rules/`, `ai/skills/` |
| **GitHub Copilot** | `.github/copilot-instructions.md`, `.github/skills/` |
| **Grok CLI** | `.cursor/prompts/plan-audit-grok.md` via `scripts/plan-review.sh` |
| **Codex CLI** | `.cursor/prompts/plan-debate-codex.md` via `scripts/plan-review.sh` |
| **Copilot CLI** | `.cursor/prompts/implementation-audit-copilot.md` via `scripts/implementation-review.sh` |

Non-trivial features: [AI development workflow](docs/04-development/ai-development-workflow.md)

## Key docs

- [README.md](README.md) — user quick start
- [docs/README.md](docs/README.md) — documentation hub
- [Contributing](docs/04-development/07-contributing.md)
- [Known dangerous commands](docs/05-maintenance/known-dangerous-commands.md)
- [Risks and gaps](docs/05-maintenance/risks-legacy-and-gaps.md)
