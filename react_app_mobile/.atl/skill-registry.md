# Skill Registry — react_app_mobile

> Index of available skills for this project. Scanned at init from user-level skill dirs
> and project-level skill dirs. Subagents receive exact paths — always read the source SKILL.md.

## Scope Legend

- **global**: installed at user/agent level (`~/.config/opencode/skills/`, etc.)
- **project**: installed in project directory (`.atl/skills/`, `.claude/skills/`, etc.)
- **system**: built-in opencode skill

## Project Conventions

| File | Scope | Notes |
|---|---|---|
| `C:/laragon/www/med-connect/react_app_mobile/AGENTS.md` | project | Session directives (Expo version note) |
| `C:/laragon/www/med-connect/react_app_mobile/CLAUDE.md` | project | Claude-specific project notes |
| `C:/laragon/www/med-connect/react_app_mobile/.claude/settings.json` | project | Claude plugin config (Expo official plugin enabled) |

## SDD Workflow Skills

| Skill | Trigger | Scope | Path |
|---|---|---|---|
| sdd-init | `sdd init`, `iniciar sdd`, `openspec init` | global | `C:/Users/carlo/.config/opencode/skills/sdd-init/SKILL.md` |
| sdd-explore | `sdd explore`, `explorar`, `requirement clarification` | global | `C:/Users/carlo/.config/opencode/skills/sdd-explore/SKILL.md` |
| sdd-propose | `sdd propose`, `proponer cambio` | global | `C:/Users/carlo/.config/opencode/skills/sdd-propose/SKILL.md` |
| sdd-spec | `sdd spec`, `escribir specs`, `delta specs` | global | `C:/Users/carlo/.config/opencode/skills/sdd-spec/SKILL.md` |
| sdd-design | `sdd design`, `diseño técnico`, `architecture` | global | `C:/Users/carlo/.config/opencode/skills/sdd-design/SKILL.md` |
| sdd-tasks | `sdd tasks`, `planificar tareas`, `implementation plan` | global | `C:/Users/carlo/.config/opencode/skills/sdd-tasks/SKILL.md` |
| sdd-apply | `sdd apply`, `implementar`, `execute tasks` | global | `C:/Users/carlo/.config/opencode/skills/sdd-apply/SKILL.md` |
| sdd-verify | `sdd verify`, `verificar`, `run tests`, `build` | global | `C:/Users/carlo/.config/opencode/skills/sdd-verify/SKILL.md` |
| sdd-archive | `sdd archive`, `archivar`, `close change` | global | `C:/Users/carlo/.config/opencode/skills/sdd-archive/SKILL.md` |
| sdd-onboard | `sdd onboard`, `onboarding`, `sdder workflow walkthrough` | global | `C:/Users/carlo/.config/opencode/skills/sdd-onboard/SKILL.md` |

## Project Support Skills

| Skill | Trigger | Scope | Path |
|---|---|---|---|
| branch-pr | `create PR`, `open PR`, `prepare PR for review` | global | `C:/Users/carlo/.config/opencode/skills/branch-pr/SKILL.md` |
| chained-pr | `chained PR`, `stacked PRs`, `split large PR` | global | `C:/Users/carlo/.config/opencode/skills/chained-pr/SKILL.md` |
| cognitive-doc-design | `design docs`, `write guide`, `README`, `RFC`, `architecture doc` | global | `C:/Users/carlo/.claude/skills/cognitive-doc-design/SKILL.md` |
| comment-writer | `write comment`, `PR feedback`, `issue reply`, `collaboration comment` | global | `C:/Users/carlo/.config/opencode/skills/comment-writer/SKILL.md` |
| go-testing | `Go tests`, `go test coverage`, `golden files` | global | `C:/Users/carlo/.claude/skills/go-testing/SKILL.md` |
| issue-creation | `create issue`, `bug report`, `feature request` | global | `C:/Users/carlo/.config/opencode/skills/issue-creation/SKILL.md` |
| judgment-day | `judgment day`, `dual review`, `adversarial review` | global | `C:/Users/carlo/.claude/skills/judgment-day/SKILL.md` |
| skill-creator | `new skill`, `create skill`, `agent instructions` | global | `C:/Users/carlo/.config/opencode/skills/skill-creator/SKILL.md` |
| skill-improver | `improve skill`, `audit skill`, `skill quality` | global | `C:/Users/carlo/.config/opencode/skills/skill-improver/SKILL.md` |
| skill-registry | `update skills`, `skill registry`, `actualizar skills` | global | `C:/Users/carlo/.config/opencode/skills/skill-registry/SKILL.md` |
| work-unit-commits | `plan commits`, `commit splitting`, `chained PRs` | global | `C:/Users/carlo/.config/opencode/skills/work-unit-commits/SKILL.md` |

## Meta Skills

| Skill | Trigger | Scope | Path |
|---|---|---|---|
| _shared | *(reference only — not invokable)* | global | `C:/Users/carlo/.config/opencode/skills/_shared/SKILL.md` |
| customize-opencode | Editing opencode config, `.opencode/`, `~/.config/opencode/`, agents, skills, MCP | global | `C:/laragon/www/med-connect/react_app_mobile/<built-in>` |

## Scan Notes

- No project-level skills detected (no `.atl/skills/`, `.claude/skills/`, etc. with SKILL.md files)
- Expo CLAUDE plugin registered: `expo@claude-plugins-official`
- Expo version constraint noted in `AGENTS.md`: use `https://docs.expo.dev/versions/v56.0.0/` for versioned docs

## How to Use

When a task matches a trigger above, load the skill with the `skill()` tool and read the
source SKILL.md at the listed path. Do not summarize — subagents receive the exact path and
must read the source of truth themselves.