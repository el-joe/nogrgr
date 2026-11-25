# Multi-Agent Failures

Last updated: 2026-11-25 (after verify batch) · Branch: **main**

| # | Failure | Status | Action |
|---|---|---|---|
| F1 | Worktree agents (P2 Localization/Billing, P3 Catalog + Commerce Pricing) were interrupted mid-task (context/agent limits; too many agents in parallel). | **Recovered** — their partial work was committed as WIP (`1d522fd`, `1c5addd`, `bc7fdb1`) and merged into `main` (`623b074`). | Resume from the pending lists in `MULTI_AGENT_PROGRESS.md`; never restart those modules. |
| F2 | Recovered WIP had not been verified since the merge. | **Resolved** (`e794206`) — `migrate:fresh` clean on `nogrgr_testing`; suite 660/660; added missing repository bindings in Localization and Billing `ModuleServiceProvider`. | Add tests for Localization/Billing repositories and `LanguageService` when those modules are built out (P2). |
| F3 | QA-P0-016 (Low): `resources/js/panels/ui/confirm.js:117` dispatches `datatable:reload` without keeping the current page. | **Resolved** — already fixed on main; `datatable.js:246` honours `detail.keepPage`. | — |
| F4 | QA-P0-015 (Low, storefront). | **Open** | Fix during P7 themes work (see QA log). |
| F5 | Flaky test in `tests/Feature/Identity/CustomerAuthTest.php` (1 of 611). | **Resolved (not reproducible)** — 9 parallel runs green; DBs/cache/Redis/throttle already isolated per worker. Earlier failure came from storefront files being written mid-run. | Re-open if it recurs. |
| F6 | Leftover `.claude/worktrees/` directory (untracked). | **Open (cosmetic)** | Clean up once confirmed all worktree branches are merged. |
| F7 | Agents hit the 5-hour usage limit mid-task (owner, texts). | **Recovered** — resumed via SendMessage after reset; no work lost (uncommitted files stay in the checkout). | Keep committing after every batch. |
| F8 | P2 gate open issues QA-P2-004…008 (PHPStan 52 errors, provisioning retry not idempotent, small UI/Figma gaps). | **Resolved** (`25ef1cf`) except PHPStan/layering in requests' in-progress files. | requests to fix before its commit. |
| F9 | Docs-only commit `ed1d834` picked up a file deletion an agent had staged (git index shared), leaving that commit broken until `03e6361`. | **Resolved** | Orchestrator commits with `git commit -- <paths>` only; agents never stage (`git add/rm`). |
| F10 | vendorrequests ran `php artisan migrate` on the dev DB (briefs say test DB only), applying other agents' pending central migrations. | **Noted** (non-destructive) | Briefs keep test-DB-only rule; dev DB may need `migrate:fresh --seed` before manual QA. |

Lesson: max 2 agents at a time, small briefs, commit after every finished batch so an interruption never loses work.
