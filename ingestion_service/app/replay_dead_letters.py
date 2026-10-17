from __future__ import annotations

import argparse

from app.settings import get_settings
from app.spool import DurableEventSpool


def main() -> int:
    parser = argparse.ArgumentParser(description="Move dead-lettered events back to pending.")
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--all", action="store_true")
    group.add_argument("--id", dest="source_event_id")
    args = parser.parse_args()
    spool = DurableEventSpool(get_settings().spool_path)
    n = spool.replay_dead_letters(None if args.all else args.source_event_id)
    spool.close()
    print(f"replayed {n} dead letter(s)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
