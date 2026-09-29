# M01 — Customer accounts and preferences

Status: module boundary confirmed by the user's catalogue correction; application not implemented or verified here. Feature inclusion in this guide does not mean every optional feature is committed to the first release.

## Catalogue scope — preserved from attachment
- **Registration and login:** Email or phone-based accounts.
- **Guest access:** Allow visitors to browse shops and use navigation without registering.
- **Customer profile:** Manage personal information, preferences, and saved items.
- **Favourites:** Save shops, products, restaurants, and offers.
- **Visit history:** View previously visited or searched shops, with customer consent.
- **Accessibility preferences:** Prefer elevators, wheelchair-accessible routes, and accessible facilities.
- **Language selection:** Bangla and English interfaces.
- **Notification preferences:** Choose which offers and updates to receive.

## Ownership and write boundaries
Own customer profile, preferences, favourites, and consented history. M12 owns authentication and access enforcement. Never duplicate credentials in this module.

## Internal components
- `accounts/`
- `preferences/`
- `favourites/`
- `visit_history/`

These are subcomponents within M01, not additional top-level modules. See [source folder](../../../modules/01_customer_accounts_preferences/README.md).

## Proposed contract
CustomerProfile {userId, language, accessibilityPreferences, notificationPreferences}; favourite target IDs must resolve to published records. History collection requires a recorded preference/consent policy.

## Dependencies
M12

Resolve IDs through the [central connecting file](../../MODULE_CONNECTIONS.md). Dependencies represent service/data contracts, not permission to import another module's database internals.

## Implementation guidance
1. Read the catalogue scope and confirm the release slice; preserve deferred features in this guide.
2. Inspect existing source, then identify owned records versus records read from other modules.
3. Define runtime-validated contracts and authorization rules using the shared integration specification.
4. Implement one complete user journey across the required subcomponents, including failure and empty states.
5. Integrate with upstream owners through explicit commands/queries; update affected consumers when contracts change.
6. Run the checks below and record evidence. Keep external integrations mocked until real access and behavior have been validated, and label every mock.

## Completion checks
- [ ] Guest access follows policy.
- [ ] Profile edits are owner-only.
- [ ] Preferences persist.
- [ ] Disabled history is not collected.
- [ ] Language/accessibility settings reach consumers..

## Progress and handoff
Record actual source changes, contract changes, verification and prototype limitations in [PROGRESS.md](../../planning/PROGRESS.md). The guide is a scope/implementation reference, not a completion claim.
