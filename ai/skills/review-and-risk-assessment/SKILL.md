---
name: review-and-risk-assessment
description: P0/P1/P2 risk triage before shipping XFlickr changes.
---

# Skill: review-and-risk-assessment

## Purpose

Classify change risk and catch blockers before merge.

## Severity levels

| Level | Examples |
|---|---|
| **P0** | Dev DB wipe risk, credential leak, data loss, broken OAuth |
| **P1** | Broken crawl/upload pipeline, migration without rollback, missing tests |
| **P2** | UI inconsistency, doc drift, minor refactors |

## Review checklist

- [ ] Docker safety: no dev stack DB commands in scripts or docs
- [ ] Tests cover happy and failure paths for changed behavior
- [ ] FormRequest validation for new inputs
- [ ] No secrets in diff
- [ ] Crawl still manual-only if touching crawl code
- [ ] Upload dedup preserved if touching transfer code

## Output

List P0/P1/P2 findings with file references and recommended fixes.

## Related skills

- `repo-quality-foundation`
- `multi-llm-plan-review`
