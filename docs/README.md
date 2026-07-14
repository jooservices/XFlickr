# XFlickr documentation

Documentation map for operators, developers, and AI agents.

## Product naming

| Name | Use |
|---|---|
| **XFlickr** | Product / application name |
| **jooservices/xflickr** | Composer package name (this repository) |

## Recommended reading order

### Operators (self-hosters)

1. [Docker installation](01-getting-started/docker-installation.md)
2. [First run](01-getting-started/first-run.md)
3. [Dashboard](02-user-guide/dashboard.md)
4. [Connections](02-user-guide/connections.md)
5. [Contacts](02-user-guide/contacts.md)
6. [Settings](02-user-guide/settings.md)

### Developers

1. [Development setup](04-development/setup.md)
2. [Coding standards](04-development/coding-standards.md)
3. [Testing](04-development/testing.md)
4. [Docker stacks](03-operations/docker-stacks.md)
5. [Modules catalog](00-architecture/modules.md) — scope, purpose, features per domain
6. [Architecture diagrams](00-architecture/architecture-diagrams.md)
7. [Contributing](04-development/07-contributing.md)
8. [AI skills maintenance](04-development/ai-skills.md) — when editing agent skills

### AI agents

1. [`AGENTS.md`](../AGENTS.md) — non-negotiable rules
2. [Modules catalog](00-architecture/modules.md) — which module owns what
3. [`ai/README.md`](../ai/README.md) — skills and tool routing
4. [AI development workflow](04-development/ai-development-workflow.md)
5. [AI skills maintenance](04-development/ai-skills.md) — canonical skill format and adapters
6. [Known dangerous commands](05-maintenance/known-dangerous-commands.md)

## Documentation sections

| Section | Audience | Contents |
|---|---|---|
| [00-architecture](00-architecture/project-overview.md) | Developers, agents | System design, [modules catalog](00-architecture/modules.md), [architecture diagrams](00-architecture/architecture-diagrams.md), data flow, package boundaries |
| [01-getting-started](01-getting-started/docker-installation.md) | Operators | Install, environments, first run |
| [02-user-guide](02-user-guide/dashboard.md) | Operators | UI pages and workflows (maps to modules in [modules catalog](00-architecture/modules.md)) |
| [03-operations](03-operations/docker-stacks.md) | Operators, DevOps | Horizon, scheduler, deploy, Docker |
| [04-development](04-development/setup.md) | Contributors | Standards, testing, PRs, AI workflow |
| [05-maintenance](05-maintenance/docker-safety.md) | Maintainers, agents | Safety, [BACKLOG](05-maintenance/BACKLOG.md), doc changelog, archive |

## Ownership rules

| Topic | Canonical location |
|---|---|
| User-facing quick start | [README.md](../README.md) |
| Install details | `01-getting-started/` |
| UI workflows | `02-user-guide/` |
| Module scope / purpose / features | [00-architecture/modules.md](00-architecture/modules.md) |
| Agent safety rules | [`AGENTS.md`](../AGENTS.md) + `05-maintenance/docker-safety.md` |
| Historical planning docs | `05-maintenance/archive/` (read-only reference) |

## Related repositories

- [`jooservices/xflickr-crawler`](https://packagist.org/packages/jooservices/xflickr-crawler) — crawling engine (owns `xflickr_*` crawl tables)
- [`jooservices/flickr`](https://packagist.org/packages/jooservices/flickr) — Flickr API SDK
