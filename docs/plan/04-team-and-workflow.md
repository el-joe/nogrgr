# Team & Workflow

Agent definitions live in `.claude/agents/*.md`; agent teams are enabled in `.claude/settings.json` (`CLAUDE_CODE_EXPERIMENTAL_AGENT_TEAMS=1`).

| Role | Agent | Owns |
|---|---|---|
| PM / Business Analyst | main session | plan, scope, client questions, acceptance criteria, phase gates |
| Software Engineer / Team Lead | `tech-lead` | architecture, shared kernel, reviews, integration |
| Senior Backend | `backend-dev` (×2–3 parallel, split by module) | modules, APIs, jobs, notifications, AI services |
| Senior Frontend | `frontend-dev` (×2–3 parallel: panels / central site / storefront) | Blade, Tailwind, JS, themes, animations |
| Senior UI/UX | `uiux-designer` | Figma admin layout, missing screens, mobile designs, tokens |
| Senior Graphic | `graphic-designer` | icons, illustrations, theme thumbnails, app icons/splash |
| Senior QA/QC | `qa-engineer` | tests, e2e, parity, regression, qa-log |
| Senior DB | `db-architect` | schema, migrations, indexes, facts/ledger, data migration |
| SEO | `seo-specialist` | technical SEO, structured data, CWV |
| Flutter | `flutter-dev` | mobile app + white-label build |

## Workflow per module
1. PM writes acceptance criteria in `01-modules.md` (old behaviour reference + changes).
2. DB architect: migrations + models reviewed.
3. Backend: enums/DTO/repo/service/controllers/tests.
4. UI/UX confirms Figma frame (or designs it); Frontend builds views.
5. QA: tests + e2e + parity → `qa-log.md`.
6. Tech Lead review → merge to `main` via branch `feat/<module>`.

## Parallelism rules
- One writer per module at a time; shared kernel changes only by Tech Lead.
- Each agent works on its own branch / worktree; merges go through Tech Lead.
- Design stays ≥ 1 week ahead of frontend.

## Branching & commits
`main` protected · `feat/<phase>-<module>` · conventional commits · each PR lists tests run.
