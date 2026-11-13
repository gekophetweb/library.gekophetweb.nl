---
name: committing-and-pushing
description: "Use whenever you create a git commit in this repository — every commit is pushed to origin straight after. Also covers which git-flow branch to commit on. Triggers on commit, save to git, check in, git flow, feature/release/hotfix branches."
---
# Committing and pushing

In this repository **every commit is pushed immediately**. A commit that only exists
locally counts as unfinished work.

## Branches (git flow)

| Branch | Purpose |
|---|---|
| `main` | Production. Only receives merges from `release/*` and `hotfix/*`. |
| `develop` | Integration branch; default target for work. |
| `feature/<name>` | New work, branched from `develop`, merged back into `develop`. |
| `bugfix/<name>` | Fixes for `develop`. |
| `release/<version>` | Stabilising a release; merged into `main` and `develop`, tagged. |
| `hotfix/<version>` | Urgent production fix from `main`; merged into `main` and `develop`, tagged. |

The git-flow settings live in `.git/config` (`gitflow.*`). The `git flow` CLI may not be
installed; plain git does the same thing:

```bash
git switch -c feature/<name> develop
```

- Never commit straight to `main` unless the user explicitly says so.
- If you are on `main` and asked to commit regular work, branch to `develop` or a
  `feature/*` branch first and say which one.

## Commit, then push

1. Check what will be committed: `git status --short` and `git diff --staged`.
   Stage specific paths — never `.env`, credentials, `vendor/` or `node_modules/`.
2. Commit with a short imperative subject line, a body explaining *why* when it isn't
   obvious, and the attribution trailer required by the current session.
3. Push right away:
   - Branch already tracks a remote: `git push`
   - New branch: `git push -u origin <branch>`
4. Confirm it landed: `git status -sb` should show no `ahead` count.
5. Report the commit hash(es), the branch and that it is pushed.

## When the push fails

- **Rejected (non-fast-forward):** someone else pushed first. Run `git fetch` and show
  the user what is new; ask before rebasing or merging. Never `--force`, and never
  `--force-with-lease` without the user's explicit OK.
- **Auth / network error:** report the error output as is. The commit stays local —
  say so clearly so it is not assumed to be on GitHub.
- **A hook fails:** fix the cause. Never use `--no-verify`.

## Releases and tags

When finishing a `release/*` or `hotfix/*`, push both long-lived branches and the tag:

```bash
git push origin main develop --follow-tags
```
