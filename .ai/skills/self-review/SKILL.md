---
name: self-review
description: "A senior-engineer final pass over your own freshly-written diff before it's committed or opened as a PR. Activates when the user asks to review my own changes, self-review this, or double-check this before I commit."
---

# Self Review

Review this as a senior engineer doing a final pass on their **own** completed work — distinct from reviewing someone else's PR, and distinct from the pre-work ticket analysis `ticket-discovery` does before code exists.

## When to Apply

- Right before committing or opening a PR, on a diff you just wrote

## Step 1: Establish Scope

Diff everything not yet on trunk: uncommitted changes plus everything committed on the current branch, against `main`.

## Step 2: Four Lenses

- **Correctness & edge cases** — does it actually do what it's supposed to, including the inputs nobody tested by hand
- **Convention adherence** — check sibling files for the established pattern rather than assuming one
- **Performance** — Eloquent N+1s, missing `with()`/eager-loading, queries running inside a Filament table column's `state()` closure or a Livewire render path
- **Security** — authorization gaps, missing input validation, sensitive data leaking into logs, audits, or responses

## Step 3: Final Altitude Pass

Deliberately stop scanning line by line. Step back and re-read the change as the person who will own this code in a year. If that pass surfaces nothing new, re-read it once more as an alternate persona — a 2am on-call engineer debugging this with zero context, or a newcomer seeing it cold for the first time.

## Step 4: Report Findings

List findings prioritized by severity. Pair every finding with a concrete fix, not just a description of the problem.

## Closing Guidance

Report high-signal issues only: real problems with a fix attached. Skip formatting and style nits — that's Pint's job, not this skill's — and skip vague unease with no remedy to offer.
