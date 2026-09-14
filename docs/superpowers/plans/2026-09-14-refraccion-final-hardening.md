# Refraccion Final Hardening Execution Index

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` with `superpowers:test-driven-development` for each behavior change.

**Goal:** Execute the operator-supplied Refraccion Final Hardening and Closure Implementation Plan in this checkout.

**Authority:** `C:/Users/jvrincon/.codex/attachments/0e48565a-efbb-4a4d-a6f8-fd7e4af8b0a2/pasted-text.txt`. This index deliberately keeps the supplied authority external so its complete task text remains the source of truth; each task brief identifies its exact section.

**Global constraints:** Preserve transactional-outbox → MySQL binlog → Debezium → Redis Streams → consumers → WebSocket ownership. Do not add polling, weaken authorization or channel boundaries, or create a second event/transition/cache/side-effect owner. Preserve at-least-once (not global exactly-once) semantics. Use RED → GREEN → REFACTOR, focused then subsystem tests, and no dependency changes unless an authority task explicitly requires them. Do not push or merge.

## Task 0: Preflight, isolation, and evidence ledger

Read authority lines 84–176. The user explicitly requested this current worktree, so record the no-new-worktree ruling after verifying branch state.

## Task 1: Make mocked alert fixtures match the real API resource

Read authority lines 177–272 before acting.

## Task 2: Establish one telemetry timestamp presentation contract

Read authority lines 273–346 before acting.

## Task 3: Freeze alert event facts at outbox-write time

Read authority lines 347–458 before acting.

## Task 4: Store replayable source fields inside each DLQ entry

Read authority lines 459–546 before acting.

## Task 5: Build a deterministic live-Docker fault-injection harness

Read authority lines 547–692 before acting.

## Task 6: Add a local Pusher-compatible WebSocket service for verification

Read authority lines 693–780 before acting.

## Task 7: Run representative MySQL EXPLAIN evidence and fix only evidenced gaps

Read authority lines 781–846 before acting.

## Task 8: Add spool health telemetry without destructive retention

Read authority lines 847–913 before acting.

## Task 9: Move SMTP transport ownership out of the Alert model

Read authority lines 914–975 before acting.

## Task 10: Classify dependency advisories and add reproducible CI audit commands

Read authority lines 976–1036 before acting.

## Task 11: Reconcile deferred security architecture without lying

Read authority lines 1037–1093 before acting.

## Task 12: Prepare required-check and branch-protection instructions

Read authority lines 1094–1130 before acting.

## Task 13: Update PLAN/evidence only after fresh verification

Read authority lines 1131–1171 before acting.

## Task 14: Run the complete verification matrix

Read authority lines 1172–EOF before acting.
