# graphify-out/ repository strategy

`graphify-out/` (knowledge graph: `graph.json`, `graph.html`, `GRAPH_REPORT.md`,
manifest/cache/cost files) is **versioned in git** — it is not gitignored.

## Rules

1. **Generated, not hand-edited.** Nothing under `graphify-out/` is written by
   hand; it is always produced by running graphify against a specific
   application SHA. Never patch it directly.
2. **Regeneration is always its own commit.** Never mix a graphify regen with
   a source/fix/feat commit. Commit message format:

   ```
   chore(graphify): regenerate graph for <application SHA>
   ```

   where `<application SHA>` is the commit the graph was generated *from*
   (e.g. `86e4331`), not the graphify commit itself.
3. **Reviewers skip `graphify-out/` diffs.** They are large, machine-generated,
   and not meaningful to line-review. The commit message's `<application SHA>`
   is what makes drift auditable — compare it against current HEAD to know if
   the graph is stale, don't diff the JSON by eye.

## Why

The `dfe7074` "actualizacion graphiphy" commit changed ~53k generated lines by
itself with no application change mixed in — that's the isolated shape every
regen commit should have. An earlier session that mixed graph regen into a
source commit made that diff unreviewable. Keep regen isolated and this stays
a non-issue.
