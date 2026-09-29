# M06 — Customer chatbot and recommendations

Status: module boundary confirmed by the user's catalogue correction; application not implemented or verified here. Feature inclusion in this guide does not mean every optional feature is committed to the first release.

## Catalogue scope — preserved from attachment
- **Natural-language search:** Handle questions such as “Where can I get a cold drink?”
- **Condition-based suggestions:** Filter suggestions by budget, category, rating, floor, and availability.
- **Shop and product recommendations:** Recommend relevant options using maintained mall data.
- **Navigation handoff:** Start directions to a recommended shop.
- **Mall FAQs:** Answer questions about opening hours, parking, facilities, and policies.
- **Offer discovery:** Find promotions matching a customer’s request.
- **Comparison assistance:** Explain differences between available options.
- **Source-linked answers:** Link answers to the relevant shop, product, or policy.
- **Human assistance:** Direct unresolved questions to the information desk.

Your proposed **RAG assistant** fits here. Live facts such as stock and prices should come from current database queries.

## Ownership and write boundaries
Own customer retrieval orchestration and generated answers. Consume M02 public discovery/live catalog data and M05 ratings; navigation handoff uses M03. Customer retrieval must not access private tenant/admin records.

## Internal components
- `retrieval/`
- `recommendations/`
- `faq/`
- `navigation_handoff/`

These are subcomponents within M06, not additional top-level modules. See [source folder](../../../modules/06_customer_chatbot_recommendations/README.md).

## Proposed contract
Answer {text, items, sourceReferences, dataAsOf, limitations}; live prices/stock are queried from authoritative records, not inferred from stale embeddings.

## Dependencies
M02, M03, M05, M07, M12

Resolve IDs through the [central connecting file](../../MODULE_CONNECTIONS.md). Dependencies represent service/data contracts, not permission to import another module's database internals.

## Implementation guidance
1. Read the catalogue scope and confirm the release slice; preserve deferred features in this guide.
2. Inspect existing source, then identify owned records versus records read from other modules.
3. Define runtime-validated contracts and authorization rules using the shared integration specification.
4. Implement one complete user journey across the required subcomponents, including failure and empty states.
5. Integrate with upstream owners through explicit commands/queries; update affected consumers when contracts change.
6. Run the checks below and record evidence. Keep external integrations mocked until real access and behavior have been validated, and label every mock.

## Completion checks
- [ ] Cold-drink query returns existing suitable items.
- [ ] Results support their claims.
- [ ] Unavailable data is admitted.
- [ ] Prompt injection cannot broaden permissions.
- [ ] Selected answer navigates to the correct entrance..

## Progress and handoff
Record actual source changes, contract changes, verification and prototype limitations in [PROGRESS.md](../../planning/PROGRESS.md). The guide is a scope/implementation reference, not a completion claim.
