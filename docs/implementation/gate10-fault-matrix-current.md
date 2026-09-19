# Gate 10 current fault matrix

Generated at: `2026-09-19T07:31:00Z`
Current SHA: `445d5333bbaff9c579ff5b52f8cf38efbdc47f21`
Run ID: `gate10-cc9ed255efb4`
Machine evidence: `.audit-e2e/results/gate10-faults-445d5333bbaff9c579ff5b52f8cf38efbdc47f21.json`

| Scenario | Result | Correlation/source event ID | Evidence assertion |
| --- | --- | --- | --- |
| A | PASS | `gate10-cc9ed255efb4-a-1a9211514f4c` | durable progress survived back container stop |
| B | FAIL | `gate10-cc9ed255efb4-b-99e471a7e1c8` | timed out waiting for CDC fault checkpoint for 1789802761176-0: predicate was false |
| C | PASS | `gate10-cc9ed255efb4-c-9f86616d9b16` | committed DB state drained after Redis recovery |
| D | PASS | `gate10-cc9ed255efb4-d-55f2fffdd88e` | poison CDC record was DLQed then ACKed without wedge |
| E | FAIL | `gate10-cc9ed255efb4-e-e77275c18989` | timed out waiting for CDC fault checkpoint for 1789802930086-0: predicate was false |

The JSON document contains before/after MySQL outbox state, Redis XLEN/XPENDING/DLQ evidence, and the sensor-reading count for every scenario.
A result is PASS only when its recorded assertions passed; this index does not replace the JSON evidence.
