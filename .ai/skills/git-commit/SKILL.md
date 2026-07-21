---
name: git-commit
description: "Stages and commits using this repo's conventional-commit format, embedding the ticket id derived from the branch name. Activates when the user asks to commit, save these changes, or make a commit."
---

# Git Commit

## When to Apply

- Only on explicit user request to commit — never as an automatic follow-on to finishing a change
- Never push as part of this. Committing and pushing are separate, explicit steps

## Step 1: Derive the Ticket Id

Extract the ticket id from the current branch name, e.g. `aeo-13-port-agentic-skill-stack` → `AEO-13` (uppercase). If the branch name carries no ticket id, ask which ticket this belongs to — don't guess.

## Step 2: Format Changed Files Only

Run `vendor/bin/pint --dirty --format agent` before staging. Never run bare `vendor/bin/pint` — that reformats the entire repo, not just this change.

## Step 3: Stage and Commit

Stage the files relevant to this change. Message format:

```
type(AEO-NN): subject
```

Types: `feat`, `fix`, `refactor`, `chore`, `docs`, `test`, `revert`.

## Step 4: Subject and Body

Keep the subject concise and imperative ("add", not "added" or "adds"). Add a body only when it adds context beyond the subject. For multi-part changes, use a bullet-point body — this repo's established style.
