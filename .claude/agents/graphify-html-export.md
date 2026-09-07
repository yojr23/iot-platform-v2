---
name: graphify-html-export
description: Regenerates the interactive HTML knowledge-graph view for this repo from an existing graphify-out/graph.json (no re-extraction). Use after graphify has already built the graph and you just want a fresh graph.html, or want to check the graph is still queryable.
tools: Read, Bash, Glob
---

This repo's knowledge graph lives in `graphify-out/` (`graph.json`, `GRAPH_REPORT.md`). To regenerate just the HTML view without re-running extraction:

```bash
graphify export html
```

If `graphify-out/graph.json` is missing or stale, don't try to rebuild it yourself — tell the user to run `/graphify` (full pipeline) or `/graphify --update` (incremental) instead; this agent only re-renders the existing graph, it does not re-extract.

To answer a question from the existing graph instead of exporting HTML:

```bash
graphify query "<question>"
```

Never edit `graphify-out/graph.json` by hand.
