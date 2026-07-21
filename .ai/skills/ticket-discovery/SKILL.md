---
name: ticket-discovery
description: "Deep, read-only investigation of a single Linear issue, cross-referenced against the codebase. Activates when the user says investigate/analyze/look into a ticket, pastes an AEO-NN id or a linear.app/aeonvolta issue URL, or asks 'what does AEO-NN need' before any code is written."
---

# Ticket Discovery

Investigate like a senior engineer doing due diligence before writing code, not like someone doing a quick lookup. The deliverable is a structured analysis, not a summary.

## When to Apply

Activate this skill when:

- The user gives a ticket id (`AEO-NN`) or a Linear issue URL and wants it understood
- The user asks what a ticket actually requires, whether it's a real bug, or how to approach it
- Before starting implementation work on a ticket, to establish root cause and scope first

## Prerequisites

- Requires the Linear MCP integration. If `mcp__linear-server__*` tools are unavailable, tell the user and stop — do not guess at ticket content.
- These tools are deferred: call `ToolSearch` with `select:<tool_name>` before first use in a session (e.g. `select:mcp__linear-server__get_issue,mcp__linear-server__list_comments`).
- **This skill is read-only end to end.** Never call `mcp__linear-server__save_issue` or `mcp__linear-server__save_comment`, and never transition status, add a comment, or move the issue. It produces analysis and stops.

## Phase 1: Identify the Ticket

Extract an id from the user's text or a pasted `https://linear.app/aeonvolta/issue/AEO-NN/...` URL. If neither is present, ask which ticket before proceeding.

## Phase 2: Gather All Ticket Context

Run these reads in parallel:

- `mcp__linear-server__get_issue` with `includeRelations: true` — description, status, priority, labels, assignee, due date, and blocking/related/duplicate relations. If the issue has sub-issues, fetch them with `mcp__linear-server__list_issues` using `parentId`; `includeRelations` does not cover them
- `mcp__linear-server__list_comments` — threads come back in one shape, with replies carrying `parentId`, so there is no separate threaded-replies call. It **is paginated**: default 50 per page, max 250 — pass `limit` and follow `cursor` until exhausted. Comments frequently hold reproduction steps and constraints the description omits, and on a busy ticket those land past the first page, so don't stop at page one
- Any attachments or images referenced in the description or comments, so visual context isn't lost. Pass the issue description or comment body to `mcp__linear-server__extract_images` to pull embedded screenshots; use `mcp__linear-server__get_attachment` with an attachment id from `get_issue` for file attachments

Before moving on, write a short summary in your own words of what's being asked, including repro steps and constraints. This is the checkpoint that catches a misread ticket before codebase time is spent on it.

## Phase 3: Investigate the Codebase

1. **Locate the affected area.** For this app (Laravel 12 + Filament v5 + Livewire v4, PostgreSQL), check: `app/Filament/` (resources, schemas, tables), Eloquent models and their relationships, `app/Enums/`, migrations, and existing coverage in `tests/Feature` / `tests/Unit`.
2. **Trace the execution path** from entry point (route, Livewire action, Filament action) to the behavior described in the ticket.
3. **Pin down the root cause** — the exact line or method responsible. For a feature request, pin down the insertion point instead.
4. **Assess blast radius** — other callers of the affected code, existing test coverage, related UI, and any schema implications.

## Phase 4: Present the Analysis

Report in this fixed structure — do not collapse or reorder it:

- **Ticket Summary** — the ask, in your own words
- **Validity Assessment** — is this really a bug, is the feature feasible as described, and any gaps or contradictions worth flagging back to the reporter
- **Probable Fix** — exact file paths; a concrete change description down to the method or line, not a vague pointer; the causal reason this is correct; a comparative reason it's lower-risk than alternatives; specific tests to add or update
- **Risk Assessment** — what could break, and what to watch after the change lands

## Tone

Be specific and direct. Cite exact paths, line numbers, and method names. State what the code does rather than hedging with "might" or "probably." When genuinely uncertain, say so explicitly instead of guessing.
