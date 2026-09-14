#!/usr/bin/env python3
"""Run the Gate 10 deterministic live-Docker fault-injection matrix.

This is intentionally an operator-run integration harness. It never starts an alternate
outbox owner, never discovers DB work for delivery, and never supplies credentials itself.
"""

from __future__ import annotations

import argparse
import json
import pathlib
import sys
from typing import Any

from lib import CommandRunner, FaultHarness, Gate10Error, HarnessConfig, utc_now


ROOT = pathlib.Path(__file__).resolve().parents[2]
SCENARIOS = ("a", "b", "c", "d", "e")


def current_sha(runner: CommandRunner) -> str:
    status = runner.run(
        ["git", "-C", str(ROOT), "status", "--porcelain=v1", "--untracked-files=no"],
        timeout=20,
    )
    if status.stdout:
        raise Gate10Error(
            "dirty tracked worktree; refuse to label bind-mounted source as current-SHA evidence"
        )
    result = runner.run(["git", "-C", str(ROOT), "rev-parse", "HEAD"], timeout=20)
    sha = result.stdout.strip()
    if len(sha) != 40:
        raise Gate10Error(f"git did not return a full SHA: {sha!r}")
    return sha


def write_json(path: pathlib.Path, document: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(document, indent=2, sort_keys=True) + "\n", encoding="utf-8")


def write_matrix(path: pathlib.Path, evidence: dict[str, Any], evidence_path: pathlib.Path) -> None:
    """Write a short human index derived exclusively from the evidence document."""
    lines = [
        "# Gate 10 current fault matrix",
        "",
        f"Generated at: `{evidence['completed_at']}`",
        f"Current SHA: `{evidence['git_sha']}`",
        f"Run ID: `{evidence['run_id']}`",
        f"Machine evidence: `{evidence_path.as_posix()}`",
        "",
        "| Scenario | Result | Correlation/source event ID | Evidence assertion |",
        "| --- | --- | --- | --- |",
    ]
    for result in evidence["scenarios"]:
        assertion = result.get("actions", {}).get("assertion", result.get("error", "no assertion emitted"))
        lines.append(
            "| {scenario} | {status} | `{event_id}` | {assertion} |".format(
                scenario=result["scenario"],
                status=result["status"],
                event_id=result["source_event_id"],
                assertion=str(assertion).replace("|", "\\|"),
            )
        )
    lines.extend([
        "",
        "The JSON document contains before/after MySQL outbox state, Redis XLEN/XPENDING/DLQ evidence, and the sensor-reading count for every scenario.",
        "A result is PASS only when its recorded assertions passed; this index does not replace the JSON evidence.",
        "",
    ])
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text("\n".join(lines), encoding="utf-8")


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--scenario", choices=("all", *SCENARIOS), default="all")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    runner = CommandRunner()
    try:
        sha = current_sha(runner)
        print(f"Gate 10 current git SHA: {sha}", flush=True)
        config = HarnessConfig.from_environment(ROOT)
        harness = FaultHarness(config)

        # Docker availability is deliberately checked before any scenario or evidence mutation.
        try:
            harness.compose.docker_available()
            harness.compose.require_ready()
        except Gate10Error as exc:
            print(f"BLOCKED: ENVIRONMENT: {exc}", file=sys.stderr)
            return 2

        selected = SCENARIOS if args.scenario == "all" else (args.scenario,)
        evidence: dict[str, Any] = {
            "schema_version": 1,
            "harness": "gate10-fault-injection",
            "git_sha": sha,
            "started_at": utc_now(),
            "run_id": harness.run_id,
            "selected_scenarios": [scenario.upper() for scenario in selected],
            "scenarios": [],
        }
        for scenario in selected:
            print(f"Gate 10 scenario {scenario.upper()} started", flush=True)
            result = harness.run_scenario(scenario)
            evidence["scenarios"].append(result)
            print(f"Gate 10 scenario {scenario.upper()} {result['status']}", flush=True)

        evidence["completed_at"] = utc_now()
        evidence["status"] = "PASS" if all(item["status"] == "PASS" for item in evidence["scenarios"]) else "FAIL"
        evidence_path = ROOT / ".audit-e2e" / "results" / f"gate10-faults-{sha}.json"
        matrix_path = ROOT / "docs" / "implementation" / "gate10-fault-matrix-current.md"
        write_json(evidence_path, evidence)
        write_matrix(matrix_path, evidence, evidence_path.relative_to(ROOT))
        print(f"Gate 10 evidence: {evidence_path}", flush=True)
        print(f"Gate 10 result: {evidence['status']}", flush=True)
        return 0 if evidence["status"] == "PASS" else 1
    except Gate10Error as exc:
        # Configuration and source-control preconditions produce no closure evidence.
        print(f"BLOCKED: ENVIRONMENT: {exc}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
