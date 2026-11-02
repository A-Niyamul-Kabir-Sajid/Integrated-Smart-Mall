# Project documentation

Before implementation, read [DOCS/00_START_HERE.md](DOCS/00_START_HERE.md), follow its reading order, and open `DOCS/modules/<ExactAppModuleName>/README.md`. Check `DOCS/CONFLICTS.md` before changing affected architecture or data ownership. `DOCS/references/` preserves source specifications; `DOCS/archive/` contains historical templates, not current implementation instructions. Follow the latest explicit user scope, including documentation-only or no-package tasks.

Setup, database and check commands: [README.md](README.md). Branch and commit conventions: [CONTRIBUTING.md](CONTRIBUTING.md).

# Laravel guidelines

Laravel Boost is installed as a dev dependency. Its framework guidelines and skills are generated into `CLAUDE.md` and `.claude/skills`; follow them as well. To generate the same guidelines for another agent, run `php artisan boost:install` and select it.
