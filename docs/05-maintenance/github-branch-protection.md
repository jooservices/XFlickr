# GitHub branch protection (dto standard)

XFlickr follows the same branch model as [`jooservices/dto`](https://github.com/jooservices/dto).

## Branch roles

| Branch | Role |
|---|---|
| `master` | **Default branch** (dto standard) — stable releases; OpenSSF Scorecard publishes here |
| `develop` | Integration branch — feature/fix PRs target here |
| `release/<version>` | Release stabilization and metadata only |
| `hotfix/*` | Urgent fixes from `master` → back-merge to `develop` |

## Ruleset: `develop & master`

Configure in GitHub → Settings → Rules → Rulesets:

- **Target branches:** `develop`, `master`
- **Bypass:** repository admins only (emergency)
- **Required status checks** (must match CI job names exactly):

| Check | Workflow job |
|---|---|
| Security Checks | `ci.yml` → `security` |
| Lint - Pint | `ci.yml` → `lint` matrix |
| Lint - PHPStan | `ci.yml` → `lint` matrix |
| Lint - Deptrac | `ci.yml` → `lint` matrix |
| Lint - AI Instructions | `ci.yml` → `lint` matrix |
| Build | `ci.yml` → `frontend` |
| Tests & Coverage | `ci.yml` → `tests` |

Optional (non-blocking): Dependency Review, OpenSSF Scorecard, Secret Scanning Disabled.

## Release flow (v1.1.0)

```bash
# From develop after CI is green
git checkout develop && git pull
git checkout -b release/1.1.0
# finalize CHANGELOG on release branch only
git push -u origin release/1.1.0
gh pr create --base master --head release/1.1.0 --title "release: Prepare v1.1.0"

# After merge to master
git checkout master && git pull
git tag v1.1.0
git push origin v1.1.0

# Sync back
git checkout develop
git merge origin/master
git push origin develop
```

Tag push triggers [`.github/workflows/release.yml`](../../.github/workflows/release.yml).

## Initial setup (one-time)

If `master` does not exist yet:

```bash
git checkout develop
git pull origin develop
git branch master
git push -u origin master
```

Then apply the ruleset above in GitHub UI.
