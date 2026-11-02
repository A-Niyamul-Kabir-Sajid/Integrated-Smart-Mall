# Contributing

Work is planned in Jira project **ISM** (Scrum board). Every change traces back to an issue key.

## Branches

- `main` is always releasable. Do not commit to it directly.
- Branch from `main` and name the branch after the work: `feature/ISM-166-shop-pagination`, `fix/ISM-210-route-crash`, `chore/ISM-141-postgres`. A branch may cover several stories of one epic, e.g. `feature/epic-00-04`.
- Merge through a pull request reviewed by the other developer. Rebase or merge `main` into the branch before review so the tests run against current code.

## Commits

Keep commits small: one logical change that builds and passes the tests. Start the subject with the Jira key, then a [Conventional Commits](https://www.conventionalcommits.org) type and scope:

```
ISM-166 feat(directory): paginate the customer shop list
```

| Type | Use for |
|---|---|
| `feat` | New behaviour |
| `fix` | Bug fix |
| `refactor` | Code change without behaviour change |
| `test` | Tests only |
| `docs` | Documentation only |
| `chore` / `build` / `ci` | Tooling, dependencies, configuration, pipelines |

- Subject in the imperative mood, at most about 72 characters, no trailing period.
- Use the body to explain why, and any follow-up the reviewer should know.
- The "GitHub for Atlassian" app links commits and branches that contain an `ISM-` key to the Jira issue.

## Before pushing

```bash
vendor/bin/pint --dirty   # format changed PHP files
php artisan test          # all tests must pass
```

## Safeguards

- Never commit `.env`, credentials, API keys or database files; `.env.example` documents every required variable with safe defaults.
- A migration that has reached `main` is immutable. Change the schema with a new migration.
- Generated files (`vendor/`, `node_modules/`, `public/build/`) are rebuilt locally and stay ignored.
