---
name: create-pr
description: "Opens a pull request as a draft by default, pulling the human-readable title from the linked Linear ticket and filling the description with a why-focused technical summary instead of a diff recap. Activates when the user asks to open a PR, create a pull request, or says this is ready for a PR."
---

# Create PR

## When to Apply

- Only when the user explicitly asks to open or create a PR — never as a side effect of finishing code or committing
- Default to a **draft** PR. Only drop the draft flag if the user explicitly says ready-for-review

## Requirements

- `gh` authenticated against this repo (verified as `Crowly34`)
- Linear MCP read access for the ticket title: `mcp__linear-server__get_issue` is deferred — call `ToolSearch` with `select:mcp__linear-server__get_issue` before first use

## Step 1: Resolve the Ticket (required)

- Extract the `AEO-NN` id from the current branch name (Linear branch names start `aeo-NN-...`). Uppercase it for display.
- Fetch the issue with `mcp__linear-server__get_issue`.
- Use its `title` field **verbatim** as the PR title — no paraphrasing, no re-casing.
- Use its `url` field for the ticket-link section — never hand-construct the URL.
- If no ticket id can be inferred from the branch name, **stop and ask** which ticket this is. Do not guess.

## Step 2: Build the PR

**Title:** `AEO-NN: <ticket title>` — the tracker id plus the ticket title exactly as written.

**Body:** read `.github/pull_request_template.md` and fill it in. It is the single source of truth for the body's shape — do not restate its sections here, and do not invent your own. If the template changes, this skill needs no edit.

Two rules the template can't enforce on its own:

- **Delete every section you have nothing real for.** The template says so in a comment; honour it. An empty heading, or one padded to look filled, is worse than an absent one.
- **`## Why` is the only section that requires actual writing.** Lead with the problem and why this is the right shape of fix. Never restate the diff as a file inventory or a line-count log — `git diff` already does that, better.

Fill `## How I verified it` from what you actually ran, not what you intended to. If nothing was verified at runtime, tick the last box and give the honest reason — an unchecked list of aspirations is worse than an admission.

## Step 3: Open It

**Preflight — non-negotiable, run before pushing or creating anything:**

- `git -C <path> remote get-url origin` must contain `Crowly34/dimak` — **abort otherwise**. Match on the `owner/repo` substring rather than a full string equality, so both the SSH (`git@github.com:Crowly34/dimak.git`) and HTTPS (`https://github.com/Crowly34/dimak.git`) forms pass.
- Current branch must not be `main` — **abort otherwise**
- The `gh pr create` call must carry an explicit `--repo Crowly34/dimak`

If any check fails: stop and tell the user exactly what mismatched. Never fall back to "try the other repo."

Then:

1. Push with `git push -u origin HEAD` — name the remote explicitly. A bare `git push` follows the branch's configured upstream, which need not be `origin`; that would push somewhere the preflight never checked. If a force-push is ever needed, confirm with the user first — never force-push silently.
2. Write the body to a temp file and pass it via `--body-file`, not an inline string — the body contains backticks and checkboxes that shell-mangle inline.
3. Create the PR:
   ```
   gh pr create --draft --title "AEO-NN: <ticket title>" --body-file <tmpfile> --repo Crowly34/dimak
   ```
   Drop `--draft` only if the user explicitly asked for ready-for-review.
4. Print the resulting PR URL back to the user.
