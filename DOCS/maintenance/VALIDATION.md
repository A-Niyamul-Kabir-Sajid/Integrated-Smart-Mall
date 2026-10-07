# Validation record

## Recorded results

Command: `python DOCS/maintenance/validate_docs.py`

```text
Checked 197 Markdown files and 519 local file links.
Module guides: 13; preserved source files: 240; deduplicated inventories: 1.
PASS
```

Additional read-only checks:

| Check | Result |
|---|---|
| Original ERD CHECKSUMS.sha256 | All 111 entries match |
| Diagram manifest targets | All 30 paths exist |
| SVG XML parsing | All 30 files parse |
| Machine-readable schema | Parses with 62 tables and 112 relationships |
| Old package path references in Markdown outside DOCS (excluding dependencies) | None found |
| Removed directory export | Exact UTF-16/UTF-8 decoded equality verified before removal |

No application tests were run: changes are documentation, relocation and a documentation validation utility only. Heading anchors, browser rendering and diagram semantics were not revalidated. Original diagram-rendering claims remain historical evidence in the preserved package.

Run the command again after documentation edits. The source inventory is an integrity baseline: future intentional changes to reference material require an explicit, documented baseline update.
