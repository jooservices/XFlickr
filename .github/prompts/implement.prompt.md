---
mode: agent
description: Implement an approved XFlickr change per AGENTS.md and final_plan.md
---

Read `AGENTS.md` and relevant `ai/skills/` before implementing.

Scope: ${input}

Rules:
- Never run database commands on local dev Docker stack
- Follow Controller → FormRequest → Service → Repository
- Run `composer test:docker` before finishing
