# Port notes

Tracking issue: **AEO-13**. Branch: `aeo-13-port-agentic-skill-stack`.

What this was: a clean-room port of a personal agentic-development skill stack out of a client repo and into this one, rewritten for Linear. Nothing was copied. One quarantined agent read the source and emitted sanitized pattern briefs; every agent that wrote a file worked only from those briefs and never had access to the source repository.

---

## What ported

| Skill | Source difficulty | Notes |
|---|---|---|
| `starting-work` | heavy | Branch naming got *simpler* — Linear hands out a canonical `gitBranchName`, so the template-derivation logic the original needed is gone. The environment bootstrap collapsed into `wt switch --create`. |
| `ticket-discovery` | medium | The report template survived nearly intact; it is the valuable part. The original's two-call comment dance collapsed to one — Linear returns threads in a single shape. |
| `create-pr` | heavy | Absorbed `pr-description` (see below). Carries the repo-safety preflight. |
| `self-review` | trivial | No tracker dependency at all. Performance lens made concrete to this stack. |
| `git-commit` | light | Encodes this repo's `type(AEO-NN):` convention and `pint --dirty`. |
| `grill-me` | trivial | Deliberately left tiny. Padding it would have ruined it. |

## What was dropped, and why

- **`pr-description` — dropped as a standalone skill, folded into `create-pr`.** Its source does not exist. It is a dangling symlink in *both* source worktrees; the target is absent from both. Writing one from scratch would have been an invention wearing a port's name, which is the one dishonest thing available in a clean-room exercise. Dropping it entirely would have lost real guidance. Folding was the honest middle. It is a clearly-headed subsection of `create-pr`, so splitting it back out later is mechanical.
- **Everything Boost owns** — untouched, as instructed.
- **Four client-specific skills** — not ported, as instructed.

---

## Corrections to the brief

Three of the premises in the original instructions did not survive verification. Flagging rather than smoothing.

**1. The `pr-description` / `create-pr` instruction was backwards.**
The brief said `pr-description` dangles in one source worktree and to take it from the other. In fact it dangles in *both* — there is no source anywhere. And `create-pr` is the one that dangles in the worktree the brief said to prefer; it exists **only** in the worktree the brief said to avoid. It was therefore sourced from the non-preferred worktree, inverting the instruction.

**2. Boost's footprint here is much smaller than assumed.**
The brief listed six Boost-owned skills to avoid. In *this* repo Boost owns two: `pest-testing` and `tailwindcss-development`. The other four exist only in the source repo. No ported skill collided with anything, so the rename-on-collision contingency was never needed. Boost's `boost.json` and both skill directories were SHA-256 checksummed before and after; all match.

**3. The Herd/ticket-regex problem is real but milder than described — and I did not apply the requested fix.**
The brief said a Linear-named branch "gets no Herd site" and asked for the regex to be extended. Observed behaviour differs: the hook has a deterministic last-resort fallback, so `aeo-999-gate-probe` produced a *working* site at `anon-985bbf.dimak.test`, correctly pathed and PHP-pinned. The cost is an unreadable hostname, not a missing environment. The same fallback was already producing `anon-` hostnames for an unrelated project on this machine before this work began.

**I deliberately did not extend the regex.** It lives in a global config shared with unrelated projects, the pattern appears at five separate call sites (the brief described one), and the payoff is cosmetic. Editing five sites in a shared file to improve a hostname's readability is a poor trade against the risk of desynchronising hooks for other projects.

**This is the decision most worth overruling if you disagree** — it is a small, contained change, and the argument for making it (readable hostnames across all your projects) is perfectly reasonable. I judged it out of scope for a port that promised not to affect other projects.

---

## The layout decision, and the reversal behind it

Skills live in **tracked `.ai/skills/`**, surfaced through **machine-local symlinks at `.claude/skills/<name>`**.

I initially decided the opposite — plain real directories in `.claude/skills/`, no `.ai/`, no symlinks — reasoning that this repo had no `.ai/` to "preserve" and that Boost's own multi-tool distribution uses duplicated real directories.

That was wrong, and the error was caught only because a structural check ran before committing: **`git status` showed nothing but the docs file.** `.gitignore:45` ignores `/.claude/` outright, under the comment *"AI tools (machine-specific config)"*, alongside `CLAUDE.md`, `AGENTS.md`, and `.mcp.json`. Zero files under `.claude/` are tracked. Boost's skills reach the repository through *generated* copies in `.agents/skills/` and `.github/skills/`.

So all six skills, written exactly where they had been specified, would have been invisible to git — never committed, never in the PR, never showable to anyone. The whole point of the exercise was to own a version you can show people.

Which means the source repo's `.ai/` + symlink layout exists for a reason none of us had articulated: **`.claude/` is machine-local, so the committed source of truth has to live somewhere else.** The original instruction to preserve that layout was right; my reasoning for overturning it was based on incomplete evidence.

`.github/skills/` was rejected as a home because it is Boost's *output* directory and the clobber risk on `boost:update` is unverified — and shipping on an unverified assumption is the specific thing this project is against.

### One manual step you need to run

Because `.claude/` is gitignored, the symlinks cannot be committed. After merging, from the main checkout:

```sh
for s in starting-work ticket-discovery create-pr self-review git-commit grill-me; do
  ln -sfn ../../.ai/skills/$s .claude/skills/$s
done
```

Until then only this worktree can see the skills. Doing it *before* the merge would create six dangling links, which check 5 of the gate would correctly reject.

---

## What the gate covers

Five checks, each with a **pass → injected-fail → restore** triplet: `gh` auth, repo identity, `wt` availability, Linear reachability, and skill-symlink integrity. Full detail in [`verification-gate.md`](verification-gate.md).

Two things it deliberately does not claim:

- **Linear network-level failure is declared UNTESTED.** The transport cannot be severed from inside the session. The fail leg proves the check discriminates a bad issue id from a good one; it does not prove behaviour when Linear is unreachable. Declared rather than faked.
- **The symlink check validates that existing links resolve, not that they exist everywhere they should.** That gap is exactly what the manual step above closes.

## What failed

**The gate had a structural blind spot, and an adversarial reviewer found it, not the checks.**

An earlier draft of `verification-gate.md` asserted that worktree provisioning left "the site, database, and PHP version all provisioned correctly." The database half was false — narrated, never observed. The database-isolation hook is gated on MySQL; this project is PostgreSQL, so it exits immediately, and the migrate/seed step inside it never runs. **Every worktree points at the same live database as the main checkout.**

Had that sentence shipped, an agent could reasonably have concluded a worktree was a safe place to run `migrate:fresh` and wiped your actual data.

The cause is worth naming: all five checks interrogate *external dependencies*. None interrogated whether the environment the skills describe matches the environment that actually gets built. `starting-work` now carries an explicit warning, and the gate document carries a section on its own failure.

## Adjudicated findings

The review produced nine findings. Eight were applied. One was **rejected**:

- *"`wt remove --force` uses a flag that does not exist."* It does exist — `-f, --force`, "Remove worktrees even if they contain untracked files." The reviewer's help output was truncated before reaching it. The empirical tell was that this exact command had already run successfully with exit 0 earlier in the session. Recorded here so it is not re-raised.

---

## Judgment calls you might disagree with

1. **Not extending the global worktrunk regex**, despite being asked to. Reasoning above. Most likely to be overruled.
2. **Folding `pr-description` into `create-pr`** rather than synthesizing one.
3. **`.ai/skills/` + symlinks** rather than un-ignoring part of `.claude/`. The alternative — a stack of negated `.gitignore` patterns to re-include specific skill directories — is brittle and fights the file's stated intent.
4. **PR title format is `AEO-NN: <ticket title>`.** The source used a three-part template including a project label; no such concept exists here, so it was dropped rather than invented.
5. **Created Linear issue AEO-13 in your workspace** to verify the write path. It is real work product, not test junk — it tracks this port and holds the gate evidence.
6. **`AEO-13` collides with existing history.** Five commits on `main` are already scoped `feat(AEO-13):` — Google Sheets sync, a faker locale — but no Linear issue AEO-13 existed until this port created one. Either you were numbering by hand or an issue was deleted. The new AEO-13 now retroactively "owns" unrelated commits. Harmless, but you may want to renumber.
7. **No tests were added.** These are Markdown instruction files with no executable surface; the project's test-enforcement rule has nothing to bind to here. The gate's triplets are the verification. Say the word if you want that argued differently.

## Blast radius

Nothing outside this repository was modified. Verified by checksum before and after: `boost.json`, both Boost skill directories, and `~/.config/worktrunk/config.toml` — all unchanged. No git or `gh` operation targeted any repository other than `Crowly34/dimak`. Nothing was pushed to `main`. The throwaway probe worktree and its Herd site were removed.
