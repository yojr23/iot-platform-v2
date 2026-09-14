"""Source-only intended behavioral tests for the Gate 10 evidence entrypoint."""

from __future__ import annotations

import unittest

from fault_injection import current_sha
from lib import CommandResult, Gate10Error


class DirtyTrackedWorktreeRunner:
    def run(self, args, *, timeout=20):
        argv = tuple(args)
        if argv[-3:] == ("status", "--porcelain=v1", "--untracked-files=no"):
            return CommandResult(argv, " M scripts/gate10/lib.py", "")
        raise AssertionError(f"unexpected command: {argv}")


class CurrentShaTest(unittest.TestCase):
    def test_dirty_tracked_worktree_cannot_be_labeled_as_current_sha_evidence(self) -> None:
        # Removing the status guard would make this test reach rev-parse and falsely permit
        # evidence for a bind-mounted source tree that differs from HEAD.
        with self.assertRaisesRegex(Gate10Error, "dirty tracked worktree"):
            current_sha(DirtyTrackedWorktreeRunner())


if __name__ == "__main__":
    unittest.main()
