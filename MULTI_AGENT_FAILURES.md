# Multi-Agent Failures

Last updated: 2026-11-25 · Branch: **main**

| # | Failure | Status | Action |
|---|---|---|---|
| F1 | Worktree agents (P2 Localization/Billing, P3 Catalog + Commerce Pricing) were interrupted mid-task (context/agent limits; too many agents in parallel). | **Recovered** — their partial work was committed as WIP (`1d522fd`, `1c5addd`, `bc7fdb1`) and merged into `main` (`623b074`). | Resume from the pending lists in `MULTI_AGENT_PROGRESS.md`; never restart those modules. |
| F2 | Recovered WIP has **not been verified**: migrations and the test suite have not run since the merge. | **Open** | Run `php artisan migrate:fresh` (test DB) and `php artisan test --parallel`; fix any failures before new work. |
| F3 | QA-P0-016 (Low): `resources/js/panels/ui/confirm.js:117` dispatches `datatable:reload` without keeping the current page. | **Open** | Change to `new CustomEvent('datatable:reload', { detail: { keepPage: true } })`. |
| F4 | QA-P0-015 (Low, storefront). | **Open** | Fix during P7 themes work (see QA log). |
| F5 | Flaky test in `tests/Feature/Identity/CustomerAuthTest.php` (1 of 611). | **Open** | Find the non-parallel-safe state (fixed names/cache keys) and use the ParallelTesting token. |
| F6 | Leftover `.claude/worktrees/` directory (untracked). | **Open (cosmetic)** | Clean up once confirmed all worktree branches are merged. |

Lesson: max 2 agents at a time, small briefs, commit after every finished batch so an interruption never loses work.
