# Decision-to-task matrix — exact live DAG snapshot

> Read-only derivation from `docs/backlog/tasks.json`, source-read at `2026-10-16T05:33:47.1592608+03:00`; SHA-256 `518CB8EA6797741B47D38816A3065852F0F3CC121BD7806ACA118FEEAF354C40` (239 task objects; source left byte-for-byte untouched). The concurrent `Status`/`Ready` snapshot: 24 `done`, 86 `blocked`, 129 `planned`, 0 leases, no pending transaction; only `A0-ARCH-001` was `currently_ready`. All decision-source statuses remain unchanged. Any ranges below are inclusive and preserve each source ID's exact zero-padding; `direct` means an exact `decision_dependencies` edge. `descendant-only` means reverse transitive closure through `depends_on`, excludes direct task IDs and done tasks. Ranges are not fuzzy feature-title matches.

Per-decision counts (direct / descendant-only, done excluded): ADR-001 1/211; ADR-002 1/176; ADR-003 5/186; ADR-004 2/1; ADR-005 2/8; ADR-006 8/8; ADR-007 4/14; ADR-008 2/12; OPEN-01 4/6; OPEN-02 6/37; OPEN-03 22/26; OPEN-04 14/42; OPEN-05 4/9; OPEN-06 2/1; OPEN-07 12/4; OPEN-08 2/8; OPEN-09 8/10; OPEN-10 10/14; OPEN-11 4/10; OPEN-12 6/3. Deduplicated across all decisions: 86 unique direct task IDs, 211 unique descendant-only IDs, 214 unique IDs in the union; per-decision closures overlap and their summed counts are not a unique unlock count.

`Unmet` lists exact task prerequisites not `done` on direct gated tasks; `other decision` lists exact additional decision dependencies. Full per-root entries appear in the appendix. `Potential` is a dependency-only counterfactual if just this decision were accepted and its direct task dependencies/other decisions were otherwise satisfied. It does **not** change a stored `blocked` status or establish readiness; coordinator/lifecycle reassessment is separate and unauthorized here. Descendant sets overlap across decisions; do not sum them as unique tasks or promised unlocks.

| Decision | Exact direct gated IDs | Remaining descendant-only IDs (done excluded) | Current direct unmet task prerequisites / additional decision dependencies | Potential immediate dependency-eligible tasks if only this decision were approved |
|---|---|---|---|---:|
| ADR-001 | `A0-CORE-900` | `A0-ARCH-002..012`, `A0-AT-14`, `A0-CORE-002..010`, `A0-DOC-002..004`, `A0-GATE-001`, `A0-SEC-002..012`, `A1-AT-01..02`, `A1-AT-11..12`, `A1-COM-001..018`, `A1-CRM-001..012`, `A1-EMP-001..004`, `A1-GATE-001`, `A1-KPI-001..008`, `A1-TASK-001..002`, `A2-AT-03`, `A2-GATE-001`, `A2-PRJ-001..012`, `A2-SALE-001..010`, `A3-AT-04..06`, `A3-DEL-001..006`, `A3-GATE-001`, `A3-INV-001..010`, `A3-MFG-001..002`, `A3-QA-001..004`, `A4-AT-07..08`, `A4-AT-15`, `A4-FIN-001..016`, `A4-GATE-001`, `A4-PAY-001..008`, `A5-AI-001..018`, `A5-AT-09..10`, `A5-AT-13`, `A5-DASH-001..010`, `A5-GATE-001`, `A5-NFR-001..016` | None on direct task; saved task is still `blocked` | 1 (`A0-CORE-900` only counterfactual; not currently ready)
| ADR-002 | `A0-ARCH-902` | `A0-GATE-001`, `A1-AT-01..02`, `A1-AT-11..12`, `A1-COM-001..018`, `A1-CRM-001..012`, `A1-EMP-001..004`, `A1-GATE-001`, `A1-KPI-001..008`, `A1-TASK-001..002`, `A2-AT-03`, `A2-GATE-001`, `A2-PRJ-001..012`, `A2-SALE-001..010`, `A3-AT-04..06`, `A3-DEL-001..006`, `A3-GATE-001`, `A3-INV-001..010`, `A3-MFG-001..002`, `A3-QA-001..004`, `A4-AT-07..08`, `A4-AT-15`, `A4-FIN-001..016`, `A4-GATE-001`, `A4-PAY-001..008`, `A5-AI-001..018`, `A5-AT-09..10`, `A5-AT-13`, `A5-DASH-001..010`, `A5-GATE-001`, `A5-NFR-001..016` | `A0-ARCH-902`: additionally ADR-003 | 0
| ADR-003 | `A0-ARCH-003..004`, `A0-ARCH-902`, `A0-CORE-007..008` | `A0-ARCH-005..012`, `A0-CORE-009..010`, `A0-GATE-001`, `A1-AT-01..02`, `A1-AT-11..12`, `A1-COM-001..018`, `A1-CRM-001..012`, `A1-EMP-001..004`, `A1-GATE-001`, `A1-KPI-001..008`, `A1-TASK-001..002`, `A2-AT-03`, `A2-GATE-001`, `A2-PRJ-001..012`, `A2-SALE-001..010`, `A3-AT-04..06`, `A3-DEL-001..006`, `A3-GATE-001`, `A3-INV-001..010`, `A3-MFG-001..002`, `A3-QA-001..004`, `A4-AT-07..08`, `A4-AT-15`, `A4-FIN-001..016`, `A4-GATE-001`, `A4-PAY-001..008`, `A5-AI-001..018`, `A5-AT-09..10`, `A5-AT-13`, `A5-DASH-001..010`, `A5-GATE-001`, `A5-NFR-001..016` | `A0-ARCH-003←A0-ARCH-002`; `A0-ARCH-004←A0-ARCH-003,A0-CORE-900`; `A0-ARCH-902` additionally ADR-002; `A0-CORE-007←A0-CORE-006`; `A0-CORE-008←A0-CORE-007,A0-CORE-900` | 0 alone; with ADR-002 only `A0-ARCH-902` becomes decision-eligible, still not task-ready by this document
| ADR-004 | `A0-DOC-003..004` | `A0-GATE-001` | `A0-DOC-003←A0-DOC-002`, `A0-DOC-004←A0-DOC-003,A0-CORE-900`; both additionally OPEN-10 | 0
| ADR-005 | `A5-NFR-009..010` | `A5-AT-13`, `A5-GATE-001`, `A5-NFR-011..016` | `A5-NFR-009←A5-NFR-008,A0-SEC-002,A0-ARCH-902`; `A5-NFR-010←A5-NFR-009,A0-CORE-900`; both additionally OPEN-08 | 0
| ADR-006 | `A1-COM-005..012` | `A1-AT-11`, `A1-COM-013..018`, `A1-GATE-001` | For COM-005..012: alternating predecessor (`A1-COM-004` then prior COM task), plus `A0-SEC-002,A0-ARCH-902` on odd-numbered IDs and `A0-CORE-900` on even; all additionally OPEN-07 | 0
| ADR-007 | `A5-AI-003..004`, `A5-AI-009..010` | `A5-AI-005..008`, `A5-AI-011..018`, `A5-AT-10`, `A5-GATE-001` | AI-003/009←prior AI task, `A0-SEC-002,A0-ARCH-902,A4-FIN-006,A3-INV-004`; AI-004/010←prior AI task, `A0-CORE-900`; all additionally OPEN-09 | 0
| ADR-008 | `A5-NFR-005..006` | `A5-AT-13`, `A5-GATE-001`, `A5-NFR-007..016` | `A5-NFR-005←A5-NFR-004,A0-SEC-002,A0-ARCH-902`; `A5-NFR-006←A5-NFR-005,A0-CORE-900`; both additionally OPEN-11 | 0
| OPEN-01 | `A4-PAY-001..004` | `A4-AT-08`, `A4-GATE-001`, `A4-PAY-005..008` | PAY-001←`A0-SEC-002,A0-ARCH-902,A4-FIN-002`; PAY-002←PAY-001,`A0-CORE-900`; PAY-003←PAY-002,`A0-SEC-002,A0-ARCH-902,A4-FIN-002` + OPEN-03; PAY-004←PAY-003,`A0-CORE-900` + OPEN-03 | 0
| OPEN-02 | `A0-SEC-005..008`, `A4-PAY-005..006` | `A0-AT-14`, `A0-GATE-001`, `A0-SEC-009..012`, `A1-AT-12`, `A1-GATE-001`, `A2-AT-03`, `A2-GATE-001`, `A2-PRJ-001..012`, `A2-SALE-001..010`, `A3-AT-06`, `A3-GATE-001`, `A4-GATE-001`, `A4-PAY-007..008` | SEC-005←SEC-004; SEC-006←SEC-005,CORE-900; SEC-007←SEC-006; SEC-008←SEC-007,CORE-900; PAY-005←PAY-004,SEC-002,ARCH-902,FIN-002 + OPEN-03; PAY-006←PAY-005,CORE-900 + OPEN-03 | 0
| OPEN-03 | `A4-FIN-001..016`, `A4-PAY-003..008` | `A4-AT-07..08`, `A4-AT-15`, `A4-GATE-001`, `A4-PAY-001..002`, `A5-AI-001..018`, `A5-AT-10`, `A5-GATE-001` | `A4-FIN-001` has no FIN predecessor; `A4-FIN-002..016` depend on the immediately preceding FIN task, with additional prerequisites shown exactly in the audit appendix; FIN-009/010 additionally OPEN-04. `A4-PAY-003..008` similarly chain from the prior PAY task; exact prerequisites and additional decisions are in the audit appendix. | 0
| OPEN-04 | `A3-INV-001..010`, `A3-MFG-001..002`, `A4-FIN-009..010` | `A3-AT-04..06`, `A3-DEL-001..006`, `A3-GATE-001`, `A3-QA-001..004`, `A4-AT-15`, `A4-FIN-011..016`, `A4-GATE-001`, `A5-AI-001..018`, `A5-AT-10`, `A5-GATE-001` | INV-001..010 chain: alternating prior INV + SEC-002/ARCH-902 on odd IDs or CORE-900 on even; MFG-001←SEC-002,ARCH-902 + OPEN-06; MFG-002←MFG-001,CORE-900 + OPEN-06; FIN-009←FIN-008,SEC-002,ARCH-902 + OPEN-03; FIN-010←FIN-009,CORE-900 + OPEN-03 | 0
| OPEN-05 | `A2-PRJ-003..004`, `A2-PRJ-011..012` | `A2-GATE-001`, `A2-PRJ-005..010`, `A3-AT-06`, `A3-GATE-001` | PRJ-003←PRJ-002,SEC-002,ARCH-902,SALE-006; PRJ-004←PRJ-003,CORE-900; PRJ-011←PRJ-010,SEC-002,ARCH-902,SALE-006 + OPEN-12; PRJ-012←PRJ-011,CORE-900 + OPEN-12 | 0
| OPEN-06 | `A3-MFG-001..002` | `A3-GATE-001` | MFG-001←SEC-002,ARCH-902 + OPEN-04; MFG-002←MFG-001,CORE-900 + OPEN-04 | 0
| OPEN-07 | `A1-COM-005..014`, `A1-COM-017..018` | `A1-AT-11`, `A1-COM-015..016`, `A1-GATE-001` | COM-005..012 use ADR-006 additionally and the alternating predecessor/SEC-002+ARCH-902 or CORE-900 pattern above; COM-013←COM-012,SEC-002,ARCH-902 + OPEN-10; COM-014←COM-013,CORE-900 + OPEN-10; COM-017←COM-016,SEC-002,ARCH-902; COM-018←COM-017,CORE-900 | 0
| OPEN-08 | `A5-NFR-009..010` | `A5-AT-13`, `A5-GATE-001`, `A5-NFR-011..016` | Same unmet task prerequisites as ADR-005; both additionally ADR-005 | 0
| OPEN-09 | `A5-AI-003..004`, `A5-AI-009..012`, `A5-AI-017..018` | `A5-AI-005..008`, `A5-AI-013..016`, `A5-AT-10`, `A5-GATE-001` | AI-003/009/011/017←prior AI task,SEC-002,ARCH-902,FIN-006,INV-004; AI-004/010/012/018←prior AI task,CORE-900; AI-003/004/009/010 additionally ADR-007; AI-011/012 additionally OPEN-10 | 0
| OPEN-10 | `A0-DOC-001..004`, `A1-COM-013..014`, `A5-AI-011..012`, `A5-NFR-015..016` | `A0-GATE-001`, `A1-AT-11`, `A1-COM-015..018`, `A1-GATE-001`, `A5-AI-013..018`, `A5-GATE-001` | DOC-001 has no unmet source dependency; DOC-002←DOC-001,CORE-900; DOC-003←DOC-002 + ADR-004; DOC-004←DOC-003,CORE-900 + ADR-004; COM-013/014 additionally OPEN-07; AI-011/012 additionally OPEN-09 with the AI prerequisites above; NFR-015←NFR-014,SEC-002,ARCH-902; NFR-016←NFR-015,CORE-900 | 1 (`A0-DOC-001` counterfactual only; not a current ready task)
| OPEN-11 | `A5-NFR-005..008` | `A5-AT-13`, `A5-GATE-001`, `A5-NFR-009..016` | NFR-005←NFR-004,SEC-002,ARCH-902 + ADR-008; NFR-006←NFR-005,CORE-900 + ADR-008; NFR-007←NFR-006,SEC-002,ARCH-902; NFR-008←NFR-007,CORE-900 | 0
| OPEN-12 | `A2-PRJ-011..012`, `A3-QA-001..004` | `A2-GATE-001`, `A3-AT-06`, `A3-GATE-001` | PRJ-011←PRJ-010,SEC-002,ARCH-902,SALE-006 + OPEN-05; PRJ-012←PRJ-011,CORE-900 + OPEN-05; QA-001←SEC-002,ARCH-902,DEL-004; QA-002←QA-001,CORE-900; QA-003←QA-002,SEC-002,ARCH-902,DEL-004; QA-004←QA-003,CORE-900 | 0

## Direct-root dependency audit — exact IDs from the same source snapshot

Each line records a direct `decision_dependencies` root. Empty cells mean no unmet task prerequisite or no other decision gate, respectively; the decision named at left is excluded from “other decision” dependencies. Every ID is copied from the JSON, with saved status shown separately.

```text
Decision | direct task ID | saved status | unmet task dependencies | other decision dependencies
ADR-001 | A0-CORE-900 | blocked |  | -
ADR-002 | A0-ARCH-902 | blocked |  | ADR-003
ADR-003 | A0-ARCH-003 | blocked | A0-ARCH-002 | -
ADR-003 | A0-ARCH-004 | blocked | A0-ARCH-003,A0-CORE-900 | -
ADR-003 | A0-ARCH-902 | blocked |  | ADR-002
ADR-003 | A0-CORE-007 | blocked | A0-CORE-006 | -
ADR-003 | A0-CORE-008 | blocked | A0-CORE-007,A0-CORE-900 | -
ADR-004 | A0-DOC-003 | blocked | A0-DOC-002 | OPEN-10
ADR-004 | A0-DOC-004 | blocked | A0-DOC-003,A0-CORE-900 | OPEN-10
ADR-005 | A5-NFR-009 | blocked | A5-NFR-008,A0-SEC-002,A0-ARCH-902 | OPEN-08
ADR-005 | A5-NFR-010 | blocked | A5-NFR-009,A0-CORE-900 | OPEN-08
ADR-006 | A1-COM-005 | blocked | A1-COM-004,A0-SEC-002,A0-ARCH-902 | OPEN-07
ADR-006 | A1-COM-006 | blocked | A1-COM-005,A0-CORE-900 | OPEN-07
ADR-006 | A1-COM-007 | blocked | A1-COM-006,A0-SEC-002,A0-ARCH-902 | OPEN-07
ADR-006 | A1-COM-008 | blocked | A1-COM-007,A0-CORE-900 | OPEN-07
ADR-006 | A1-COM-009 | blocked | A1-COM-008,A0-SEC-002,A0-ARCH-902 | OPEN-07
ADR-006 | A1-COM-010 | blocked | A1-COM-009,A0-CORE-900 | OPEN-07
ADR-006 | A1-COM-011 | blocked | A1-COM-010,A0-SEC-002,A0-ARCH-902 | OPEN-07
ADR-006 | A1-COM-012 | blocked | A1-COM-011,A0-CORE-900 | OPEN-07
ADR-007 | A5-AI-003 | blocked | A5-AI-002,A0-SEC-002,A0-ARCH-902,A4-FIN-006,A3-INV-004 | OPEN-09
ADR-007 | A5-AI-004 | blocked | A5-AI-003,A0-CORE-900 | OPEN-09
ADR-007 | A5-AI-009 | blocked | A5-AI-008,A0-SEC-002,A0-ARCH-902,A4-FIN-006,A3-INV-004 | OPEN-09
ADR-007 | A5-AI-010 | blocked | A5-AI-009,A0-CORE-900 | OPEN-09
ADR-008 | A5-NFR-005 | blocked | A5-NFR-004,A0-SEC-002,A0-ARCH-902 | OPEN-11
ADR-008 | A5-NFR-006 | blocked | A5-NFR-005,A0-CORE-900 | OPEN-11
OPEN-01 | A4-PAY-001 | blocked | A0-SEC-002,A0-ARCH-902,A4-FIN-002 | -
OPEN-01 | A4-PAY-002 | blocked | A4-PAY-001,A0-CORE-900 | -
OPEN-01 | A4-PAY-003 | blocked | A4-PAY-002,A0-SEC-002,A0-ARCH-902,A4-FIN-002 | OPEN-03
OPEN-01 | A4-PAY-004 | blocked | A4-PAY-003,A0-CORE-900 | OPEN-03
OPEN-02 | A0-SEC-005 | blocked | A0-SEC-004 | -
OPEN-02 | A0-SEC-006 | blocked | A0-SEC-005,A0-CORE-900 | -
OPEN-02 | A0-SEC-007 | blocked | A0-SEC-006 | -
OPEN-02 | A0-SEC-008 | blocked | A0-SEC-007,A0-CORE-900 | -
OPEN-02 | A4-PAY-005 | blocked | A4-PAY-004,A0-SEC-002,A0-ARCH-902,A4-FIN-002 | OPEN-03
OPEN-02 | A4-PAY-006 | blocked | A4-PAY-005,A0-CORE-900 | OPEN-03
OPEN-03 | A4-FIN-001 | blocked | A0-SEC-002,A0-ARCH-902 | -
OPEN-03 | A4-FIN-002 | blocked | A4-FIN-001,A0-CORE-900 | -
OPEN-03 | A4-FIN-003 | blocked | A4-FIN-002,A0-SEC-002,A0-ARCH-902 | -
OPEN-03 | A4-FIN-004 | blocked | A4-FIN-003,A0-CORE-900 | -
OPEN-03 | A4-FIN-005 | blocked | A4-FIN-004,A0-SEC-002,A0-ARCH-902 | -
OPEN-03 | A4-FIN-006 | blocked | A4-FIN-005,A0-CORE-900 | -
OPEN-03 | A4-FIN-007 | blocked | A4-FIN-006,A0-SEC-002,A0-ARCH-902 | -
OPEN-03 | A4-FIN-008 | blocked | A4-FIN-007,A0-CORE-900 | -
OPEN-03 | A4-FIN-009 | blocked | A4-FIN-008,A0-SEC-002,A0-ARCH-902 | OPEN-04
OPEN-03 | A4-FIN-010 | blocked | A4-FIN-009,A0-CORE-900 | OPEN-04
OPEN-03 | A4-FIN-011 | blocked | A4-FIN-010,A0-SEC-002,A0-ARCH-902 | -
OPEN-03 | A4-FIN-012 | blocked | A4-FIN-011,A0-CORE-900 | -
OPEN-03 | A4-FIN-013 | blocked | A4-FIN-012,A0-SEC-002,A0-ARCH-902 | -
OPEN-03 | A4-FIN-014 | blocked | A4-FIN-013,A0-CORE-900 | -
OPEN-03 | A4-FIN-015 | blocked | A4-FIN-014,A0-SEC-002,A0-ARCH-902 | -
OPEN-03 | A4-FIN-016 | blocked | A4-FIN-015,A0-CORE-900 | -
OPEN-03 | A4-PAY-003 | blocked | A4-PAY-002,A0-SEC-002,A0-ARCH-902,A4-FIN-002 | OPEN-01
OPEN-03 | A4-PAY-004 | blocked | A4-PAY-003,A0-CORE-900 | OPEN-01
OPEN-03 | A4-PAY-005 | blocked | A4-PAY-004,A0-SEC-002,A0-ARCH-902,A4-FIN-002 | OPEN-02
OPEN-03 | A4-PAY-006 | blocked | A4-PAY-005,A0-CORE-900 | OPEN-02
OPEN-03 | A4-PAY-007 | blocked | A4-PAY-006,A0-SEC-002,A0-ARCH-902,A4-FIN-002 | -
OPEN-03 | A4-PAY-008 | blocked | A4-PAY-007,A0-CORE-900 | -
OPEN-04 | A3-INV-001 | blocked | A0-SEC-002,A0-ARCH-902 | -
OPEN-04 | A3-INV-002 | blocked | A3-INV-001,A0-CORE-900 | -
OPEN-04 | A3-INV-003 | blocked | A3-INV-002,A0-SEC-002,A0-ARCH-902 | -
OPEN-04 | A3-INV-004 | blocked | A3-INV-003,A0-CORE-900 | -
OPEN-04 | A3-INV-005 | blocked | A3-INV-004,A0-SEC-002,A0-ARCH-902 | -
OPEN-04 | A3-INV-006 | blocked | A3-INV-005,A0-CORE-900 | -
OPEN-04 | A3-INV-007 | blocked | A3-INV-006,A0-SEC-002,A0-ARCH-902 | -
OPEN-04 | A3-INV-008 | blocked | A3-INV-007,A0-CORE-900 | -
OPEN-04 | A3-INV-009 | blocked | A3-INV-008,A0-SEC-002,A0-ARCH-902 | -
OPEN-04 | A3-INV-010 | blocked | A3-INV-009,A0-CORE-900 | -
OPEN-04 | A3-MFG-001 | blocked | A0-SEC-002,A0-ARCH-902 | OPEN-06
OPEN-04 | A3-MFG-002 | blocked | A3-MFG-001,A0-CORE-900 | OPEN-06
OPEN-04 | A4-FIN-009 | blocked | A4-FIN-008,A0-SEC-002,A0-ARCH-902 | OPEN-03
OPEN-04 | A4-FIN-010 | blocked | A4-FIN-009,A0-CORE-900 | OPEN-03
OPEN-05 | A2-PRJ-003 | blocked | A2-PRJ-002,A0-SEC-002,A0-ARCH-902,A2-SALE-006 | -
OPEN-05 | A2-PRJ-004 | blocked | A2-PRJ-003,A0-CORE-900 | -
OPEN-05 | A2-PRJ-011 | blocked | A2-PRJ-010,A0-SEC-002,A0-ARCH-902,A2-SALE-006 | OPEN-12
OPEN-05 | A2-PRJ-012 | blocked | A2-PRJ-011,A0-CORE-900 | OPEN-12
OPEN-06 | A3-MFG-001 | blocked | A0-SEC-002,A0-ARCH-902 | OPEN-04
OPEN-06 | A3-MFG-002 | blocked | A3-MFG-001,A0-CORE-900 | OPEN-04
OPEN-07 | A1-COM-005 | blocked | A1-COM-004,A0-SEC-002,A0-ARCH-902 | ADR-006
OPEN-07 | A1-COM-006 | blocked | A1-COM-005,A0-CORE-900 | ADR-006
OPEN-07 | A1-COM-007 | blocked | A1-COM-006,A0-SEC-002,A0-ARCH-902 | ADR-006
OPEN-07 | A1-COM-008 | blocked | A1-COM-007,A0-CORE-900 | ADR-006
OPEN-07 | A1-COM-009 | blocked | A1-COM-008,A0-SEC-002,A0-ARCH-902 | ADR-006
OPEN-07 | A1-COM-010 | blocked | A1-COM-009,A0-CORE-900 | ADR-006
OPEN-07 | A1-COM-011 | blocked | A1-COM-010,A0-SEC-002,A0-ARCH-902 | ADR-006
OPEN-07 | A1-COM-012 | blocked | A1-COM-011,A0-CORE-900 | ADR-006
OPEN-07 | A1-COM-013 | blocked | A1-COM-012,A0-SEC-002,A0-ARCH-902 | OPEN-10
OPEN-07 | A1-COM-014 | blocked | A1-COM-013,A0-CORE-900 | OPEN-10
OPEN-07 | A1-COM-017 | blocked | A1-COM-016,A0-SEC-002,A0-ARCH-902 | -
OPEN-07 | A1-COM-018 | blocked | A1-COM-017,A0-CORE-900 | -
OPEN-08 | A5-NFR-009 | blocked | A5-NFR-008,A0-SEC-002,A0-ARCH-902 | ADR-005
OPEN-08 | A5-NFR-010 | blocked | A5-NFR-009,A0-CORE-900 | ADR-005
OPEN-09 | A5-AI-003 | blocked | A5-AI-002,A0-SEC-002,A0-ARCH-902,A4-FIN-006,A3-INV-004 | ADR-007
OPEN-09 | A5-AI-004 | blocked | A5-AI-003,A0-CORE-900 | ADR-007
OPEN-09 | A5-AI-009 | blocked | A5-AI-008,A0-SEC-002,A0-ARCH-902,A4-FIN-006,A3-INV-004 | ADR-007
OPEN-09 | A5-AI-010 | blocked | A5-AI-009,A0-CORE-900 | ADR-007
OPEN-09 | A5-AI-011 | blocked | A5-AI-010,A0-SEC-002,A0-ARCH-902,A4-FIN-006,A3-INV-004 | OPEN-10
OPEN-09 | A5-AI-012 | blocked | A5-AI-011,A0-CORE-900 | OPEN-10
OPEN-09 | A5-AI-017 | blocked | A5-AI-016,A0-SEC-002,A0-ARCH-902,A4-FIN-006,A3-INV-004 | -
OPEN-09 | A5-AI-018 | blocked | A5-AI-017,A0-CORE-900 | -
OPEN-10 | A0-DOC-001 | blocked |  | -
OPEN-10 | A0-DOC-002 | blocked | A0-DOC-001,A0-CORE-900 | -
OPEN-10 | A0-DOC-003 | blocked | A0-DOC-002 | ADR-004
OPEN-10 | A0-DOC-004 | blocked | A0-DOC-003,A0-CORE-900 | ADR-004
OPEN-10 | A1-COM-013 | blocked | A1-COM-012,A0-SEC-002,A0-ARCH-902 | OPEN-07
OPEN-10 | A1-COM-014 | blocked | A1-COM-013,A0-CORE-900 | OPEN-07
OPEN-10 | A5-AI-011 | blocked | A5-AI-010,A0-SEC-002,A0-ARCH-902,A4-FIN-006,A3-INV-004 | OPEN-09
OPEN-10 | A5-AI-012 | blocked | A5-AI-011,A0-CORE-900 | OPEN-09
OPEN-10 | A5-NFR-015 | blocked | A5-NFR-014,A0-SEC-002,A0-ARCH-902 | -
OPEN-10 | A5-NFR-016 | blocked | A5-NFR-015,A0-CORE-900 | -
OPEN-11 | A5-NFR-005 | blocked | A5-NFR-004,A0-SEC-002,A0-ARCH-902 | ADR-008
OPEN-11 | A5-NFR-006 | blocked | A5-NFR-005,A0-CORE-900 | ADR-008
OPEN-11 | A5-NFR-007 | blocked | A5-NFR-006,A0-SEC-002,A0-ARCH-902 | -
OPEN-11 | A5-NFR-008 | blocked | A5-NFR-007,A0-CORE-900 | -
OPEN-12 | A2-PRJ-011 | blocked | A2-PRJ-010,A0-SEC-002,A0-ARCH-902,A2-SALE-006 | OPEN-05
OPEN-12 | A2-PRJ-012 | blocked | A2-PRJ-011,A0-CORE-900 | OPEN-05
OPEN-12 | A3-QA-001 | blocked | A0-SEC-002,A0-ARCH-902,A3-DEL-004 | -
OPEN-12 | A3-QA-002 | blocked | A3-QA-001,A0-CORE-900 | -
OPEN-12 | A3-QA-003 | blocked | A3-QA-002,A0-SEC-002,A0-ARCH-902,A3-DEL-004 | -
OPEN-12 | A3-QA-004 | blocked | A3-QA-003,A0-CORE-900 | -
```

## Static DAG gaps â€” report only, no inferred edges

The matrix reflects only recorded `decision_dependencies` and `depends_on`. PRD MUST/phase acceptance requirements, and documented change-control needs, may express semantic gates not directly encoded as decision edges. In particular, channel scope must not be downgraded from Email/SMS MUST by these docs; practical AI audit and its acceptance gate remain distinct from an AI provider decision; private encrypted-object-storage language must be reconciled with local-storage direction; and PRD `OPEN` business policy / change-control approval may matter beyond a row's direct task dependencies. Treat these as semantic review items, not added DAG edges or computed direct task IDs. No dependency repair or gate amendment was made.

`A0-ARCH-001` is the sole current `ready` report: prerequisite `A0-ARCH-901` is `done`, with no decision dependencies. This documentation sprint neither claims its implementation nor authorizes `Claim`, app changes, tests, or lifecycle mutation.
