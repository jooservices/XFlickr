# AI skills maintenance

Canonical skill bodies live in [`ai/skills/`](../../ai/skills/).

## Rules

1. **Single source of truth** — write skill content in `ai/skills/<name>/SKILL.md` only.
2. **Stubs for Copilot** — `.github/skills/<name>/SKILL.md` contains YAML frontmatter and a link to the canonical path.
3. **Cursor rules are thin** — `.cursor/rules/*.mdc` point to skills; do not duplicate full policy prose.
4. **Update order** — canonical skill first, then adapters, then run `composer instructions:verify`.

## Skill file format

Canonical skills use this structure. Safety-critical skills (e.g. `xflickr-docker-testing`) may use a `## Hard rule` section instead of repeating `## Purpose` prose, but must still include `## Purpose`, `## When to use`, and `## Related skills`.

```markdown
# Skill: <name>

## Purpose
...

## When to use
...

## Required context
...          # optional

## Rules
...          # or domain-specific sections

## Steps
...          # optional

## Related skills
...
```

## When to add a skill

- New domain with non-obvious safety rules (e.g. storage drivers, transfer dedup)
- Repeated agent mistakes that need explicit guardrails
- New workflow that agents must follow consistently

## When not to add a skill

- One-off task instructions (put in the PR or plan instead)
- Duplicating content already in `AGENTS.md` (link instead)

## Adapter layers

| Layer | Path |
|---|---|
| Canonical | `ai/skills/` |
| Copilot stubs | `.github/skills/` |
| Cursor rules | `.cursor/rules/` |
| Cursor skill stubs | `.cursor/skills/` (stub only — links to `ai/skills/`) |
| Claude commands | `.claude/commands/` |
| Copilot prompts | `.github/prompts/` |
| Multi-LLM prompts | `.cursor/prompts/` |

## Verification

```bash
composer instructions:verify
```

See also [`ai/README.md`](../../ai/README.md).
