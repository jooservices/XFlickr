# Pull request standard

## Branch naming

- `feature/<short-name>` — new features
- `fix/<short-name>` — bug fixes
- `docs/<short-name>` — documentation only
- `chore/<short-name>` — tooling, dependencies

## PR title

Use a clear, descriptive title. Prefer conventional style:

- `feat: add contact bulk upload filter`
- `fix: skip already-uploaded files in upload job`
- `docs: add storage browse user guide`

## PR body

Explain:

1. **Summary** — what changed
2. **Why** — motivation or bug context
3. **Verification** — tests run, manual checks
4. **Risk** — breaking changes, migration needs
5. **Screenshots** — for UI changes

Use `.github/pull_request_template.md` when available.

## Before opening

- [ ] `composer test:docker` passes
- [ ] `npm run typecheck` passes (if frontend changed)
- [ ] `composer instructions:verify` passes
- [ ] Docs and `CHANGELOG.md` `[Unreleased]` updated for user-visible changes
- [ ] No secrets in diff

## Review expectations

- Keep PRs focused — one feature or fix per PR.
- Respond to review feedback with additional commits (not force-push unless requested).

## Commit authorship

All commits must use `Viet Vu <jooservices@gmail.com>` as author and committer.

- Never commit as Cursor Agent or other AI identities.
- Do not add `Co-authored-by` trailers for AI tools.
