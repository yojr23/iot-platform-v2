# Gate 10 current fault matrix

Generated at: `2026-09-19T21:39:18Z`
Current SHA: `96d9523e9b3d7eef8dd4d88b4ae8292f2684ee7b`
Run ID: `gate10-8b26634f9ba1`
Machine evidence: `.audit-e2e/results/gate10-faults-96d9523e9b3d7eef8dd4d88b4ae8292f2684ee7b.json`

| Scenario | Result | Correlation/source event ID | Evidence assertion |
| --- | --- | --- | --- |
| A | PASS | `gate10-8b26634f9ba1-a-386df8b3e8c2` | durable progress survived back container stop |
| B | PASS | `gate10-8b26634f9ba1-b-9506592d973f` | XADD-before-XACK pending entry was reclaimed without logical duplicate |
| C | PASS | `gate10-8b26634f9ba1-c-3204d9425b16` | committed DB state drained after Redis recovery |
| D | PASS | `gate10-8b26634f9ba1-d-69e8b66ce6dc` | poison CDC record was DLQed then ACKed without wedge |
| E | PASS | `gate10-8b26634f9ba1-e-c39cd28eb13d` | dead-consumer pending work was reclaimed with no logical duplicate |

The JSON document contains before/after MySQL outbox state, Redis XLEN/XPENDING/DLQ evidence, and the sensor-reading count for every scenario.
A result is PASS only when its recorded assertions passed; this index does not replace the JSON evidence.
