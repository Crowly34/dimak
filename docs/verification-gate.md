# The verification gate

## The rule

**Any assertion never observed failing is untested.**

A check that has only ever passed is indistinguishable from a check that cannot fail. Both return green. The difference only shows up on the day something is actually broken — which is the one day you needed the check to work.

So a gate is not "a set of checks that pass." A gate is a set of checks **each of which has been observed rejecting a bad input.**

## What that means in practice

Every check in this gate records a three-legged evidence triplet:

| leg | what it proves |
|---|---|
| **pass** | the check accepts a known-good input |
| **injected fail** | the check *rejects* a deliberately-broken input, with a real captured exit code |
| **restore** | the injection left no residue — the system is back where it started |

The third leg is not ceremony. An injection that quietly persists turns a verification exercise into an outage. Every mechanism below was chosen to be reversible by construction — scoped to a single invocation, or performed against a decoy rather than the real thing.

A narrated failure ("this would fail if the token were bad") is **not evidence**. Only a captured non-zero exit or a real error response counts.

## The five checks

### 1. `gh` authentication

| leg | mechanism | observed |
|---|---|---|
| pass | `gh api user -q .login` | `Crowly34`, exit 0 |
| fail | `GH_TOKEN=<garbage> gh api user` | `Bad credentials (HTTP 401)`, exit 1 |
| restore | re-run pass leg | `Crowly34`, exit 0 |

The env var overrides the keychain **for that one process**. Nothing on disk changes.

`gh auth logout` was deliberately **not** used. It destroys the stored token, and re-authenticating requires a browser — an irreversible action taken on behalf of an absent user. A fault injection that needs a human to undo it is not a fault injection; it is damage.

### 2. Repository identity

| leg | mechanism | observed |
|---|---|---|
| pass | `git -C <repo> remote get-url origin` vs. expected | match |
| fail | same check pointed at a scratch repo with a foreign remote | mismatch → refused |
| restore | re-run pass leg | match |

This is the check that keeps work from landing in the wrong repository. The fail leg deliberately pointed it at a foreign remote and confirmed it refuses. The real repository's remote was never modified — the decoy was a throwaway `git init` in a scratch directory, deleted afterward.

Every skill that pushes or opens a PR asserts this first, and aborts on mismatch. Aborting means *stop and tell the user*, never "try somewhere else."

### 3. `wt` (worktrunk) availability

| leg | mechanism | exit |
|---|---|---|
| pass | `wt -C <repo> list` | 0 |
| fail | `WORKTRUNK_BIN=/nonexistent/wt wt -C <repo> list` | 127 |
| restore | `wt -C <repo> list` | 0 |

`wt` is a shell function wrapping `${WORKTRUNK_BIN:-wt}`, so one env var breaks it for exactly one invocation.

### 4. Linear reachability

| leg | mechanism | observed |
|---|---|---|
| pass (read) | list teams; fetch a known issue | team + issue returned |
| pass (write) | create an issue; comment on it | both succeeded |
| fail | fetch a non-existent issue id | `Could not find referenced Issue` (400) |

The write path is verified with real artifacts rather than junk: the issue created *is* this port's tracking ticket, and the comment on it *is* this gate's evidence log. Verification and work product are the same object.

**Declared UNTESTED:** true network-level unreachability. The MCP transport cannot be severed from inside the session, so the "Linear is down" branch has never been observed executing. The fail leg above proves the check *discriminates* between a valid and invalid issue — it does not prove behaviour under transport failure.

This is recorded rather than papered over. By this document's own rule, that branch is untested, and saying so is the only honest option.

### 5. Skill symlink integrity

The skills live in tracked `.ai/skills/`. Each is surfaced to the agent tooling through a symlink at `.claude/skills/<name>`, because `/.claude/` is gitignored on this project — it is machine-local config by design, so anything written only there would never be committed.

| leg | mechanism | observed |
|---|---|---|
| pass | resolve every `.claude/skills/*` symlink | 6/6 ok, exit 0 |
| fail | move one link's target directory away | that entry reports `DANGLING`, exit 1 |
| restore | move the target back | 6/6 ok, exit 0 |

This check exists because the layout it validates has a known failure mode: in the codebase this pattern was adapted from, two symlinks had been dangling for months and nobody noticed. A link nobody dereferences is an assertion nobody observes failing — the same defect this document is about, expressed in a filesystem.

**Known limit of this check, stated rather than glossed:** it verifies that the links which exist resolve. It does *not* verify that they exist everywhere they need to. They currently exist only in the worktree where this work was done. The main checkout has none, and since new worktrees are seeded by copying ignored files from the main checkout, worktrees created before that is fixed will not see these skills at all.

The one-time fix, run from the main checkout after this branch merges:

```sh
cd /path/to/dimak
for s in starting-work ticket-discovery create-pr self-review git-commit grill-me; do
  ln -sfn ../../.ai/skills/$s .claude/skills/$s
done
```

Because `.claude/` is gitignored, this is inherently a per-machine bootstrap step — it cannot be committed, only documented. Running it before the merge would create six dangling links, which is precisely what check 5 is designed to reject.

## A worked example of the rule catching itself

The first attempt at probing `wt switch` ran `wt switch main` from the repository root — and reported success.

It was worthless. The command "moved" to the directory already occupied, so it would have reported success whether or not the mechanism worked at all. An assertion that cannot distinguish between the two outcomes it is meant to distinguish is not a weak test; it is not a test.

Re-run properly — switching to a genuinely different worktree — it surfaced two distinct failure modes that the original assumption had merged into one:

1. **Piping breaks it silently.** `wt switch x | tail` runs the shell function in a subshell, so the directive-file `source` that performs the directory change is discarded. The command prints success and does nothing.
2. **Directory changes do not survive a tool-call boundary.** Unpiped, `wt switch` *does* move the shell. The next tool call is back at the original path.

Hence the rule every skill here follows: **use `wt -C <absolute-path>`, pass paths forward explicitly, and never pipe `wt switch`.**

This is the gate's own methodology applied to the gate. The check that looked fine was the one that was broken.

## What this gate did not catch

An earlier draft of this document claimed the worktree provisioning left "the site, database, and PHP version all provisioned correctly."

The database part was false, and it was false in the specific way this document exists to warn about: it was **narrated, not observed**. The provisioning hook prints a database-creation and migrate/seed block during setup, so it looks like it ran. It does not. The block is gated on the project using MySQL, and this project uses PostgreSQL, so it exits immediately — and the migrate/seed step lives inside that same gate, so it never runs either.

The consequence is not cosmetic. Every worktree keeps the copied `DB_DATABASE` value and points at the **same live database** as the main checkout. Any agent that believed the original sentence would have concluded a worktree was a safe place to run `migrate:fresh`.

One command disproved it:

```sh
grep -E '^DB_DATABASE=' /path/to/main/.env /path/to/worktree/.env   # identical
```

Two things are worth drawing out of this:

1. **The gate was applied rigorously to five external dependencies and not at all to the deliverable's own mechanics.** Every check above interrogates something outside this repository. Nothing interrogated whether the environment the skills describe matches the environment that actually gets built. The blind spot was structural, not accidental.
2. **It was caught by an adversarial reader, not by the checks.** A reviewer whose explicit job was to find what everyone else missed found it in the document that preaches against exactly this error. That is the argument for keeping such a reader in the loop even when the automated checks are green — and the argument against treating a green gate as proof of correctness.

The rule this document opens with cuts both ways. An assertion never observed failing is untested, and that applies to the assertions *in the gate itself*.

## Regression, not perfection

The gate blocks on **regressions** — things this work made worse. It does not block on inherited conditions it merely noticed.

Worked example: local worktree hostnames come out as `anon-<hash>.dimak.test` rather than something readable, because the provisioning hook's ticket-id pattern does not recognise this tracker's identifier format and falls through to a deterministic fallback. The site itself is linked correctly and the PHP version is pinned correctly; the cost is a hostname nobody can read. The same fallback was already in use elsewhere on the machine before this work started.

So it is recorded as **pre-existing, report-only**. It does not block. The shared configuration it originates from was not modified, because that configuration is used by unrelated projects and a change there would carry blast radius far outside this repository for a cosmetic gain.

An absolute quality bar applied to inherited debt produces permanent noise that never clears, and a gate that always shows red is as uninformative as one that always shows green.
