# AGENTS.md — XFlickr

Read this file before running commands or changing code in this repository.

## Must-read first

1. This file (`AGENTS.md`)
2. [Documentation hub](docs/README.md)
3. [AI skills map](ai/README.md)
4. [Docker safety](docs/05-maintenance/docker-safety.md)

---

## Docker scripts (two stacks)

Two public entry points — **AI may only use `test.sh`** (see Docker policy below).

| Script | Purpose | Who |
|--------|---------|-----|
| [`scripts/dev.sh`](scripts/dev.sh) | Dev stack (`docker-compose.dev.yml`, hot reload) | **Operator only** |
| [`scripts/test.sh`](scripts/test.sh) | Quality gates + isolated test stack | **AI + CI only** |

Internal helpers live under [`scripts/lib/`](scripts/lib/) (`compose-dev.sh`, `compose-test.sh`, `dev/`, `test/`).

### Docker policy (agents)

- **Dev = operator local workspace.** Persistent data (MySQL, MongoDB volumes) is **owned by the operator — AI must never touch it.**
- **AI MAY ONLY run:** `bash scripts/test.sh` (`gate`, `gate:lint`, `gate:test`, `gate:ci`, `ensure-hooks`, `verify-hooks`, `up`, `down`, `down --volumes`)
- **AI MUST NOT run:** `scripts/dev.sh`, `scripts/deploy.sh`, bare `docker compose` against dev, or **`docker exec xflickr-dev-*`** (any command — including `php artisan test`, `migrate`, `migrate:fresh`). Tests run **only** via `scripts/test.sh` on the **test stack** (`xflickr-test-*`).
- **If `gate:test` fails:** report to the operator — **never** workaround by running PHPUnit inside a dev container (`RefreshDatabase` + `DB_HOST=mysql` wipes dev MySQL). Code guard: `Tests\Support\RefreshDatabaseGuard`.
- After implementing changes, **tell the operator** which dev commands to run — see [`ai/skills/operator-dev-docker/SKILL.md`](ai/skills/operator-dev-docker/SKILL.md).

**Operator dev reload (no DB impact)** — AI copies this block to the user, does not execute:

```bash
docker exec xflickr-dev-horizon-1 php artisan horizon:terminate
bash scripts/dev.sh restart-frontend   # if UI bundle is stale
bash scripts/dev.sh reload             # rebuild assets + restart workers
```

**Operator stack start:** `bash scripts/dev.sh up` or `seed` — never bare `docker compose -f docker-compose.dev.yml up` (wrong project/volume names).

Dev subcommands: `up` (migrate only), `seed`, `refresh` (MySQL schema wipe + admin), `reset-data` / `down --volumes` (all volumes), `down`, `reload`, `restart-frontend`, `refresh-frontend`.

Test stack uses `docker-compose.test.yml` (project `xflickr-test`). Named volumes: `xflickr-test-*`.

### Commit and push (non-negotiable for AI)

1. **Once per clone:** `bash scripts/test.sh ensure-hooks`
2. **Before every commit:** `bash scripts/test.sh gate` must pass
3. **Before every push:** `bash scripts/test.sh gate:ci` must pass
4. **Never** use `SKIP_HOOKS=1` unless the operator explicitly requests a bypass in the same message.

---

## Project identity

**XFlickr** — self-hosted Flickr archive manager (Laravel 12 + React 19 + Inertia 3).

- Crawling: `jooservices/xflickr-crawler`
- App credentials: **MongoDB** via laravel-config (`xflickr_app.*`, `storage_app.*`)
- Connected accounts: **MySQL** (`flickr_accounts`, `storage_accounts`)
- Local URL: **http://localhost:8082** (override `APP_HOST_PORT` in `.env`)
- **Authentication** — all web/API routes require session login. Tests auto-authenticate via `TestCase::authenticateAsAdmin()` unless a test uses `IgnoresAuthentication`. Initial admin password: `ADMIN_PASSWORD` env (see `.env.example`); change with `php artisan xflickr:user:password`.

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

1. **Never touch local dev databases** — see Docker policy above.
2. **Tests only via `scripts/test.sh`** — isolated test stack (`sqlite :memory:`, MongoDB `xflickr_test`).
3. **Spider mode** — opt-in only (`spider.enabled` in runtime config); depth/caps in Settings → General; see [constraints](docs/05-maintenance/constraints.md) and [spider-mode](docs/02-user-guide/spider-mode.md). Manual per-contact crawls remain valid.
4. **Inspect source first** — do not invent routes, commands, or behavior.
5. **Minimize scope** — focused diffs; match existing conventions.

## Quality gates

Before opening a PR:

```bash
bash scripts/test.sh gate
bash scripts/test.sh gate:ci    # before push
```

CI enforces **60% PHPUnit coverage** via `gate:ci` / `composer test:docker:coverage`.

**Never:**

```bash
docker exec xflickr-dev-app-1 php artisan test
docker compose exec app php artisan test
scripts/dev.sh
```

## Skill routing

| Task type | Read first |
|---|---|
| Any change | `repo-quality-foundation` |
| Docker / DB commands | `xflickr-docker-testing`, `docker-dev-stack-safety`, `operator-dev-docker` |
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
- [ ] Tests pass: `bash scripts/test.sh gate`
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
- [Risks and gaps](docs/05-maintenance/BACKLOG.md)
