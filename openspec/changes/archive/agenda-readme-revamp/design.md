# Design: agenda-readme-revamp

## Context

`README.md` lines 323-343 are a frozen snapshot of the cycle-1 agenda-core chained split (9 cycles ago). The section claims `**Feature-complete pending sdd-verify.**` for an archived cycle and references the obsolete `feat/filament-panels` branch. Cycles 4-9 closed drifts in OTHER README sections but left Status untouched — it is now the most stale block. This change is doc-only: 0 route, 0 schema, 0 behavior changes.

## Design decision

**Option D — h3 split** (user's pick over the recommended Option C).

| Option | Choice | Tradeoff | Decision |
|---|---|---|---|
| A | Text-only update (~5 LOC) | Cheapest, but flat prose with PR table stays confusing | Rejected |
| B | Single-block refactor (~12 LOC) | Tighter, still flat prose | Rejected |
| C | Checklist + state block (~15 LOC) | Recommended; discards per-concern organization | Rejected |
| **D** | **4 h3 subsections (~30 LOC)** | **Heavier vertical real estate, but organizes by concern; scales** | **Selected** |

**Why D**: organizes heterogeneous info (counts, claims, navigation, history) for visual scanning; the README already uses h3s in Filament v5 (6 h3s, lines 225-321), PR 4 (2 h3s, 177-211), REST API (7 h3s, 362-516) — D aligns with the existing h2/h3 document convention. Each h3 is updatable independently.

**Tradeoffs of D** (honest): diverges from "most sections are h2-only"; heavier on LOC than C (28-33 vs 15-20).

## Section structure

### Current (lines 323-343, 21 lines)

`## Status — agenda-core` + `**Feature-complete pending sdd-verify.**` + 5-row PR table referencing `feat/filament-panels` + trailing `Next step is sdd-verify` paragraph. All stale by 8 cycles.

### New (illustrative; sdd-spec owns exact wording)

The h3 enumeration + order is the contract; sdd-apply applies canonical content from `proposal.md` §"What changes":

```markdown
## Status

med-connect is feature-complete for the agenda core + HTTP scope, with the doc-contract test pattern continuously closing README and PRD drift since cycle 4. All 9 SDD cycles are archived.

### Build status
- 18 routes (3 auth + 15 public); unchanged since agenda-readme-cleanup
- 13 migrations + 12 Eloquent models + 13 factories (from agenda-core)
- Filament v5 panels: /admin (UserResource, SpecialtyResource) + /doctor (dashboard only)

### Test status
- SQLite: 147 passed + 4 skipped (571 assertions)
- MariaDB: 151 passed + 0 projected (env unavailable this session)
- 0 canonical spec drift

### SDD state
- 13 capabilities, 34 reqs, 138 scenarios (per agenda-prd-update archive-report)
- 9 archived changes; 0 active (only archive/ under openspec/changes/)

### Roadmap
- [x] agenda-core / agenda-http / agenda-test-coverage / agenda-readme-drift /
      agenda-spec-drift / agenda-api-dedup / agenda-readme-cleanup /
      env-section-overhaul / agenda-prd-update
```

**LOC**: 30-32 (within 30 LOC soft budget + 2-3 LOC buffer).

## File changes

| File | Action | Description |
|---|---|---|
| `README.md` | Modify | Replace lines 323-343 with 4-h3-section content (~30 LOC) |
| `tests/Feature/Docs/ReadmeApiSurfaceTest.php` | Modify | Add 3 scenarios (12, 13, 14) |

## Test extension plan (3 ADDED scenarios)

All in `tests/Feature/Docs/ReadmeApiSurfaceTest.php`, all `it()` (matches 11-scenario style; cycle 9 DEV-4 lesson). The +9 line shift from 21→30 moves 5 existing anchor lines; TEST-FIX commit per `agenda-readme-cleanup` precedent (commit 0461eb3).

| # | Drift | Pattern |
|---|---|---|
| 12 | Line-precise (`## Status`, no subtitle) | `expect($lines[322])->toBe('## Status')` exact match |
| 13 | h3 enum + order (4 in fixed order) | h2-bounded `array_slice` + `preg_match('/^### (.+)$/')`; `expect($headings)->toBe([...])` `===` order check |
| 14 | Negative (no `Feature-complete pending` / `sdd-verify`) | `array_slice($lines, 322, 40)` over 40-line window; `expect()->not->toContain(...)` × 2 |

## Risks & mitigations

| Risk | Lik | Imp | Mitigation |
|---|---|---|---|
| Line-number drift between proposal and RED / +9 line shift breaks 5 of 11 existing scenarios | Med | Low | Verify line numbers in RED; TEST-FIX commit per `agenda-readme-cleanup` precedent |
| New section exceeds 30 LOC | Low | Low | Trim redundant bullets; explore forecast 28-33 LOC |
| h3 convention break confuses readers | Low | Low | Filament v5 section already uses h3 (6 h3s); precedent exists |
| Pint auto-fix during sdd-verify | High | Low | sdd-archive bundles with verify-report (per obs #95 DSC-1) |

## Cumulative state forecast

- **Pre-archive** (cycle 10 baseline per obs #98): 13/34/138/18.
- **Post-archive** (1 new sub-cap `agenda/readme-revamp`): 14/35/141/18. Routes unchanged at 18.

## Review budget (D1)

30 LOC soft budget for new README section. 400-line hard budget: ~30 LOC README + ~50 LOC test = ~80 LOC total. **Chained PRs NOT recommended; single PR.** **Decision needed before apply**: No. **400-line budget risk**: Low.

## References

- `openspec/changes/agenda-readme-revamp/proposal.md` (128 lines, canonical content locked)
- `openspec/changes/agenda-readme-revamp/tasks.md` (126 lines)
- engram obs #98 (cycle 9 baseline), #100 (sdd-propose), #101 (sdd-tasks)
- `tests/Feature/Docs/ReadmeApiSurfaceTest.php` (11 existing scenarios, lines 155-170 negative-assertion example)
- `README.md` lines 323-343 (current Status section)
