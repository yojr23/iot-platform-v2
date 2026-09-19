# Gate 10 current fault matrix

Generated at: `2026-09-19T16:37:13Z`
Current SHA: `3dc771bea8e93544bfb426fd9f3fa7e113484505`
Run ID: `gate10-421106ecf81d`
Machine evidence: `.audit-e2e/results/gate10-faults-3dc771bea8e93544bfb426fd9f3fa7e113484505.json`

| Scenario | Result | Correlation/source event ID | Evidence assertion |
| --- | --- | --- | --- |
| A | PASS | `gate10-421106ecf81d-a-69d0e4c24cae` | durable progress survived back container stop |
| B | PASS | `gate10-421106ecf81d-b-c046d66a0ea9` | XADD-before-XACK pending entry was reclaimed without logical duplicate |
| C | PASS | `gate10-421106ecf81d-c-9318a7573ef3` | committed DB state drained after Redis recovery |
| D | PASS | `gate10-421106ecf81d-d-13f543cdb9e0` | poison CDC record was DLQed then ACKed without wedge |
| E | PASS | `gate10-421106ecf81d-e-1674a34009b2` | dead-consumer pending work was reclaimed with no logical duplicate |

The JSON document contains before/after MySQL outbox state, Redis XLEN/XPENDING/DLQ evidence, and the sensor-reading count for every scenario.
A result is PASS only when its recorded assertions passed; this index does not replace the JSON evidence.
