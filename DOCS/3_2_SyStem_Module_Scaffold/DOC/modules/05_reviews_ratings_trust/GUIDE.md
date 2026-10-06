# M05 — Reviews, ratings, and trust

Status: module boundary confirmed by the user's catalogue correction; application not implemented or verified here. Feature inclusion in this guide does not mean every optional feature is committed to the first release.

## Catalogue scope — preserved from attachment
- **Normal reviews:** Let customers review shops without purchase verification.
- **Purchase-verified reviews:** Mark reviews linked to a valid purchase.
- **Product and shop ratings:** Keep product quality and shop service feedback distinguishable.
- **Rating categories:** Rate service, value, cleanliness, and other relevant aspects.
- **Receipt QR or verification token:** Issue a purchase-linked credential that customers can redeem.
- **Duplicate prevention:** Limit repeated verification or reviews for the same eligible purchase.
- **Shop responses:** Allow shop owners to respond to feedback.
- **Review reporting:** Let users flag spam, abuse, or suspicious reviews.
- **Moderation:** Provide a review queue and recorded moderation decisions.
- **Rating breakdown:** Show rating distribution and distinguish verified from unverified feedback.

For your hash-key idea, **verification should rely on a server-validated purchase record and an unguessable or securely signed token**. Combining a product ID with a mall key alone does not establish that a purchase happened.

## Ownership and write boundaries
Own reviews, rating policy, purchase-proof credentials/redemption, responses and moderation decisions. Verify against M04 purchase evidence, including authorized external purchase registration through M08. Do not treat token possession without valid issuance as proof.

## Internal components
- `normal_reviews/`
- `purchase_verification/`
- `ratings/`
- `responses/`
- `moderation/`

These are subcomponents within M05, not additional top-level modules. See [source folder](../../../modules/05_reviews_ratings_trust/README.md).

## Proposed contract
Review {id, targetType, targetId, rating, verificationId?, status}; Proof {purchaseId, eligibleTarget, tokenDigest, expiry, redeemedBy?}. Never accept client verified=true as authority.

## Dependencies
M01, M04, M08, M12

Resolve IDs through the [central connecting file](../../MODULE_CONNECTIONS.md). Dependencies represent service/data contracts, not permission to import another module's database internals.

## Implementation guidance
1. Read the catalogue scope and confirm the release slice; preserve deferred features in this guide.
2. Inspect existing source, then identify owned records versus records read from other modules.
3. Define runtime-validated contracts and authorization rules using the shared integration specification.
4. Implement one complete user journey across the required subcomponents, including failure and empty states.
5. Integrate with upstream owners through explicit commands/queries; update affected consumers when contracts change.
6. Run the checks below and record evidence. Keep external integrations mocked until real access and behavior have been validated, and label every mock.

## Completion checks
- [ ] Forged/expired/reused proof fails.
- [ ] Concurrent redemption permits one eligible binding.
- [ ] Product and shop feedback remain distinguishable.
- [ ] Moderation affects aggregates consistently.
- [ ] Shop replies cannot edit customer content..

## Progress and handoff
Record actual source changes, contract changes, verification and prototype limitations in [PROGRESS.md](../../planning/PROGRESS.md). The guide is a scope/implementation reference, not a completion claim.
