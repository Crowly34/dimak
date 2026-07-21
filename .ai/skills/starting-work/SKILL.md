---
name: starting-work
description: "Standardizes how a session begins new work on a Linear-tracked task — clean base, canonical branch naming, and an isolated per-branch dev environment by default. Activates when starting a new feature, picking up a Linear issue, saying 'let's work on AEO-NN', beginning a fix, or asking to create a branch or worktree for new work."
---

# Starting Work

## Overview

New work starts from a clean, up-to-date base, uses a canonical branch name, and — by default — runs in an isolated worktree so parallel work streams never collide. This skill never writes to Linear.

## When to Apply

Use this skill when:

- Starting work on a Linear issue (`AEO-NN`) or a new self-contained change with no issue
- The session is about to create a branch or worktree for new work

Do NOT use this skill when:

- Continuing work already in progress on an existing branch/worktree
- Doing read-only exploration or research (no code changes intended)
- The user explicitly asks to work directly on `main`

## Decision Point: Isolated Environment vs Plain Branch

Default to the isolated worktree path (Step 5). Drop to the plain-branch fallback (Step 5b) only for a small, self-contained change that won't run in parallel with other work (a one-file fix, a copy tweak). **Confirm the choice with the user before proceeding** — state which path you're taking and why.

## Step 1 — Pre-Flight Check

Run `git status` in the current checkout. If the working tree is dirty, stop and surface it — do not stash or discard automatically; ask the user how to handle it. Note the current branch.

## Step 2 — Sync Trunk

Switch to `main` first, then fetch and fast-forward it from `origin`. Do not run the merge from whatever branch happens to be checked out — if that branch is an ancestor of `origin/main`, a fast-forward silently moves its pointer and destroys it as a distinct ref.

If a fast-forward isn't possible (local `main` has diverged), **stop and surface this to the user** — do not force-reset or merge on their behalf.

Step 5b in particular depends on this: `git checkout -b` cuts from current HEAD, so it inherits whatever you were sitting on unless `main` is synced and checked out first.

## Step 3 — Identify the Unit of Work

Prefer a Linear issue reference. If the tool isn't loaded yet, call `ToolSearch` with `select:mcp__linear-server__get_issue` first, then fetch the issue **read-only** — this skill never creates, updates, or comments on Linear issues. If there's no issue, fall back to a short user-supplied slug.

## Step 4 — Derive and Confirm the Branch Name

If working from a Linear issue, use its `gitBranchName` field **verbatim** — it's already the canonical lowercase name (e.g. `aeo-13-port-agentic-skill-stack`). Do not hand-construct or reformat it. If working from a user-supplied slug, keep it short and kebab-case.

Confirm the branch name with the user before creating anything — downstream tooling (Step 5) and the eventual PR both key off it.

## Step 5 — Environment Bootstrap (default path)

Run:

```
wt switch --create <gitBranchName>
```

`wt`'s post-create hooks handle the sibling worktree, `.env` copy, dependency install, asset build, PHP version pinning, and a Herd site. dimak has no bootstrap script of its own — don't invent one or re-run these steps manually.

Verify it worked by checking the reported worktree path exists and the Herd site responds. Expect an opaque hostname (`anon-<hash>.dimak.test`) — a pre-existing quirk of the shared global hook config, not a bug in this setup.

> **The worktree does NOT get its own database.** The database-isolation hook is gated on MySQL and this project runs PostgreSQL, so it exits immediately — and the migrate/seed step lives inside that same hook, so it never runs either. The copied `.env` keeps `DB_DATABASE=dimak`: every worktree points at the **same live database** as the main checkout.
>
> This holds for the whole life of the worktree:
> - **Never run `migrate:fresh`, `db:wipe`, or a destructive seeder from a worktree** — it wipes the shared database, including the main checkout's data.
> - Two worktrees running tests or seeds concurrently will corrupt each other's state.
> - If the task genuinely needs an isolated database, create one and repoint this worktree's `DB_DATABASE` before doing anything else.

## Step 5b — Fallback Path (plain branch, no isolation)

For small, self-contained changes only:

```
git checkout -b <gitBranchName>
```

No worktree, no separate database, no separate Herd site — work continues in the current checkout.

## Step 6 — Orient and Hand Off

If you took the isolated path, **do not `cd` and do not pipe `wt switch`** — piping runs it in a subshell and silently discards the directory change, and a directory change from one tool call never survives into the next anyway. Instead:

- Report the new worktree's absolute path and its Herd URL to the user.
- Use `wt -C <absolute-path> <command>` for any further `wt` operations, and pass that same absolute path forward explicitly to every subsequent tool call in this session (Bash, Read, Edit, etc.) — never assume cwd carried over.

All subsequent work for this task operates from that path. If deeper investigation of the codebase is needed before writing code, hand off to the `ticket-discovery` skill.

## Quick Reference

| Step | Command |
|------|---------|
| Pre-flight | `git status` |
| Sync trunk | `git fetch origin && git switch main && git merge --ff-only origin/main` |
| Fetch issue (read-only) | `ToolSearch select:mcp__linear-server__get_issue` → `mcp__linear-server__get_issue` |
| Bootstrap (default) | `wt switch --create <gitBranchName>` |
| Bootstrap (fallback) | `git checkout -b <gitBranchName>` |
| Any further `wt` call | `wt -C <absolute-path> <command>` |
| Remove worktree later | `wt -C <worktree-path> remove --force` |

## Common Mistakes

- Re-running bootstrap steps (`composer install`, `npm ci`, asset build) that `wt`'s post-create hooks already ran
- Assuming the worktree has its own database — it does not; see the warning in Step 5 before running anything destructive
- Continuing to operate on the original checkout after a worktree was created
- Branching from a dirty or stale `main` instead of stopping to surface it
- Hand-constructing a branch name instead of using Linear's `gitBranchName` verbatim
- Piping `wt switch` (e.g. into `tail`) — this silently drops the directory change
- Assuming a previous `wt switch`/`cd` left later tool calls in the new directory — it doesn't; always pass the absolute path explicitly
- Skipping the naming/approach confirmation with the user before creating the branch
- Doing deep codebase investigation here instead of handing off to `ticket-discovery`
- Writing to Linear (comments, status updates) during setup — this skill is read-only against the tracker
