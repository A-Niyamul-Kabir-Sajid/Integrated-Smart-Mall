# Project documentation

Before implementation, read [DOCS/00_START_HERE.md](DOCS/00_START_HERE.md), follow its reading order, and open `DOCS/modules/<ExactAppModuleName>/README.md`. Check `DOCS/CONFLICTS.md` before changing affected architecture or data ownership. `DOCS/references/` preserves source specifications; `DOCS/archive/` contains historical templates, not current implementation instructions. Follow the latest explicit user scope, including documentation-only or no-package tasks.

Setup, database and check commands: [README.md](README.md). Branch and commit conventions: [CONTRIBUTING.md](CONTRIBUTING.md).

# Laravel guidelines

Laravel Boost is installed as a dev dependency. Its framework guidelines and skills are generated into `CLAUDE.md` and `.claude/skills`; follow them as well. To generate the same guidelines for another agent, run `php artisan boost:install` and select it.

# Git workflow rules

- Do not push to GitHub (no `git push`, no pull requests) unless the user explicitly asks for it in that task.
- Do not add Claude, or any AI assistant, as a co-author. No `Co-Authored-By` trailer in commit messages and no "Generated with" line in pull request descriptions.
- Commit story by story: one or more commits per Jira story, each starting with that story's key (e.g. `ISM-66 feat(purchase): ...`). Never mix two stories in one commit.
- Do not merge branches unless the user asks. Work on a feature branch, not on `main`.
- If Jira cannot be reached, tell the user instead of guessing story keys or scope.
