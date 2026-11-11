# Guide

Hands-on material for people working on Integrated Smart Mall. It is not requirements documentation (that lives in [DOCS](../DOCS/00_START_HERE.md)).

| Folder | Contains |
|---|---|
| [TestByYourself](TestByYourself/README.md) | Step-by-step manual checks you run in the browser, one file per feature area, kept in step with the automated tests |

## Rule for every implementation (people and AI agents)

A feature slice is not finished until **both** exist:

1. **Automatic tests** in `tests/` (see [DOCS/09_TESTING.md](../DOCS/09_TESTING.md)).
2. **A manual check file** in [TestByYourself](TestByYourself/README.md): a new file for a new area, or new steps in the existing file for that area. Start from [_TEMPLATE.md](TestByYourself/_TEMPLATE.md) and add the file to the index in [TestByYourself/README.md](TestByYourself/README.md).

The manual file is **self-contained**: it repeats its own setup, creates the accounts and data it needs, writes every URL in full, and never says "do another file first". It lists the exact accounts, inputs and the expected result of each step, plus the automated test files that cover the same behaviour. If a slice changes behaviour that an existing file describes, update that file in the same change.
