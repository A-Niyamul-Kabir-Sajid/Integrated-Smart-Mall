# Central module connections

The 12 numbered sections of the user's uploaded catalogue are the authoritative top-level module boundaries. This replaces the earlier 13-module organization. Every original feature bullet is retained in its module guide. Subcomponents are implementation subdivisions, not independent top-level modules.

| ID | Module | Guidance | Source folder |
|---|---|---|---|
| M01 | Customer accounts and preferences | [Guide](modules/01_customer_accounts_preferences/GUIDE.md) | [Source](../modules/01_customer_accounts_preferences/README.md) |
| M02 | Mall directory and product discovery | [Guide](modules/02_mall_directory_product_discovery/GUIDE.md) | [Source](../modules/02_mall_directory_product_discovery/README.md) |
| M03 | Indoor maps and navigation | [Guide](modules/03_indoor_maps_navigation/GUIDE.md) | [Source](../modules/03_indoor_maps_navigation/README.md) |
| M04 | Shopping and purchase assistance | [Guide](modules/04_shopping_purchase_assistance/GUIDE.md) | [Source](../modules/04_shopping_purchase_assistance/README.md) |
| M05 | Reviews, ratings, and trust | [Guide](modules/05_reviews_ratings_trust/GUIDE.md) | [Source](../modules/05_reviews_ratings_trust/README.md) |
| M06 | Customer chatbot and recommendations | [Guide](modules/06_customer_chatbot_recommendations/GUIDE.md) | [Source](../modules/06_customer_chatbot_recommendations/README.md) |
| M07 | Parking, facilities, and visitor support | [Guide](modules/07_parking_facilities_visitor_support/GUIDE.md) | [Source](../modules/07_parking_facilities_visitor_support/README.md) |
| M08 | Shop-owner operations | [Guide](modules/08_shop_owner_operations/GUIDE.md) | [Source](../modules/08_shop_owner_operations/README.md) |
| M09 | Shop-owner analytics | [Guide](modules/09_shop_owner_analytics/GUIDE.md) | [Source](../modules/09_shop_owner_analytics/README.md) |
| M10 | Mall-owner administration | [Guide](modules/10_mall_owner_administration/GUIDE.md) | [Source](../modules/10_mall_owner_administration/README.md) |
| M11 | Mall-owner analytics and decision support | [Guide](modules/11_mall_owner_analytics_decision_support/GUIDE.md) | [Source](../modules/11_mall_owner_analytics_decision_support/README.md) |
| M12 | Shared platform requirements | [Guide](modules/12_shared_platform_requirements/GUIDE.md) | [Source](../modules/12_shared_platform_requirements/README.md) |

## Main connections
| Flow | Collaborating modules | Ownership rule |
|---|---|---|
| Customer discovery/navigation | M01 → M02 → M03 | M08 owns edited catalog; M10 owns approved mall structure |
| Shopping and collection | M04 ↔ M08 | M04 owns order/purchase state; M08 performs authorized fulfilment |
| Purchase-verified review | M08 → M04 purchase record → M05 proof/review | M05 validates authoritative purchase evidence |
| Customer assistant | M02 + M05 + M07 → M06 → M03 | M06 answers from public evidence and hands off destinations |
| Visitor issue resolution | M07 ↔ M10 | One case identity from intake to assignment/resolution |
| Shop oversight | M04 + M05 + M08 → M09 | Shop-scoped analytics; no duplicate transactions |
| Mall oversight | M03 + M04 + M05 + M07 + M08 + M10 → M11 | Mall-wide permission checks and evidence-based metrics |
| Platform services | M12 supports M01–M11 | Shared security/infrastructure; business ownership stays explicit |

M02→M03 links mean directory results can start navigation; they do not require a circular code import. M03 can accept a destination ID/coordinate resolved through an application coordinator. Commands and queries should be one-directional at the implementation layer even when business workflows communicate both ways.

## Subcomponents moved from the earlier structure
- Identity/authentication foundation → M12; customer profile and preferences → M01.
- Maps, checkpoints, routing, navigation UI and WebAR → M03.
- Purchase-proof verification, normal reviews and ratings → M05.
- Customer RAG assistant → M06.
- Shop dashboard operations → M08; shop reports/forecasting → M09.
- Mall administrative operations → M10; monthly reports, mall forecasting and admin assistant → M11.

## Canonical references
- [Source layout](integration/SOURCE_LAYOUT.md)
- [Contracts and ownership](integration/CONTRACTS.md)
- [Integration scenarios](integration/SCENARIOS.md)
- [Decisions](project/DECISIONS.md)
- [Progress](planning/PROGRESS.md)
- [Build order](planning/BUILD_ORDER.md)
- [Security/verification](quality/SECURITY_VERIFICATION.md)
- [Original catalogue](project/FEATURE_CATALOGUE.md)

Before changing shared behavior, identify its owner, update its contract and affected consumers, then record actual evidence. Keep optional/deferred features documented without calling them implemented.
