# Project documentation

Before implementation, read [DOCS/00_START_HERE.md](DOCS/00_START_HERE.md), follow its reading order, and open `DOCS/modules/<ExactAppModuleName>/README.md`. Check `DOCS/CONFLICTS.md` before changing affected architecture or data ownership. `DOCS/references/` preserves source specifications; `DOCS/archive/` contains historical templates, not current implementation instructions. Follow the latest explicit user scope, including documentation-only or no-package tasks.

Setup, database and check commands: [README.md](README.md). Branch and commit conventions: [CONTRIBUTING.md](CONTRIBUTING.md).

# Testing rule: automatic tests and manual guide together

Every implemented slice ships **two** kinds of tests, in the same change:

1. Automatic tests under `tests/` (see [DOCS/09_TESTING.md](DOCS/09_TESTING.md)).
2. A manual check file in [guide/TestByYourself/](guide/TestByYourself/README.md): add steps to the existing file for that area, or copy [_TEMPLATE.md](guide/TestByYourself/_TEMPLATE.md) to the next free `NN_area.md`, and list it in that folder's index and in `14_automated_tests.md`. The file must be self-contained (its own setup, its own accounts and data, every URL written in full as http://127.0.0.1:8000/..., no "do file NN first"). Each step names the account, URL or button, input and expected result, what must be refused, and the automatic test files that cover it. Never put real secrets or purchase codes in it.

A slice is not done until both exist and `guide/TestByYourself` matches the behaviour. Update the file when behaviour changes. If a seller-side or admin screen does not exist yet, give the manual file a helper (see `guide/TestByYourself/scripts/`) instead of skipping the check.

# Laravel guidelines

Laravel Boost is installed as a dev dependency. Its framework guidelines and skills are generated into `CLAUDE.md` and `.claude/skills`; follow them as well. To generate the same guidelines for another agent, run `php artisan boost:install` and select it.

# Git workflow rules

- Do not push to GitHub (no `git push`, no pull requests) unless the user explicitly asks for it in that task.
- Do not add Claude, or any AI assistant, as a co-author. No `Co-Authored-By` trailer in commit messages and no "Generated with" line in pull request descriptions.
- Commit story by story: one or more commits per Jira story, each starting with that story's key (e.g. `ISM-66 feat(purchase): ...`). Never mix two stories in one commit.
- Do not merge branches unless the user asks. Work on a feature branch, not on `main`.
- If Jira cannot be reached, tell the user instead of guessing story keys or scope.
