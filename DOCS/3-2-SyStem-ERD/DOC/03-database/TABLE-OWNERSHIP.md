# Table ownership

Each real table has exactly one owner. External boxes in other diagrams are references to these tables.

| Table | Module | Scope | Purpose |
| --- | --- | --- | --- |
| APP_USER | M01 Accounts and Access | Core | One account across all customer, shop, and mall interfaces. |
| ROLE | M01 Accounts and Access | Core | Role catalogue with an explicit authorization scope. |
| USER_ROLE | M01 Accounts and Access | Core | Platform-wide account role assignment. |
| MALL_MEMBERSHIP | M01 Accounts and Access | Core | A user with one particular role within one mall. |
| SHOP_MEMBERSHIP | M01 Accounts and Access | Core | A user with one particular role within one shop. |
| AUTH_SESSION | M01 Accounts and Access | Core | Revocable login sessions; not route sessions. |
| MALL | M02 Mall and Shop Directory | Core | A mall boundary used for directory, map, and permission ownership. |
| FLOOR | M02 Mall and Shop Directory | Core | Physical level of a mall, independent of any map version. |
| SHOP_CATEGORY | M02 Mall and Shop Directory | Core | Shop classification such as food, clothing, or electronics. |
| SHOP | M02 Mall and Shop Directory | Core | One shop business in one mall; locations are represented separately. |
| SHOP_UNIT | M02 Mall and Shop Directory | Core | A physical unit occupied by a shop; a shop can occupy several floors. |
| SHOP_OPENING_HOUR | M02 Mall and Shop Directory | Core | Weekly opening intervals, including split opening periods. |
| FACILITY | M02 Mall and Shop Directory | Core | Public destinations such as toilets, information desks, or prayer rooms. |
| PRODUCT_CATEGORY | M03 Products and Catalogue | Core | Searchable product taxonomy; distinct from shop categories. |
| PRODUCT | M03 Products and Catalogue | Core | Shared product definition that can be sold by multiple shops. |
| PRODUCT_VARIANT | M03 Products and Catalogue | Core | A purchasable version such as size, volume, or colour. |
| SHOP_PRODUCT | M03 Products and Catalogue | Core | A shop-specific listing and price for one product variant. |
| PRODUCT_IMAGE | M03 Products and Catalogue | Core | Shared product images ordered for presentation. |
| INVENTORY_BALANCE | M03 Products and Catalogue | Optional | Optional latest inventory snapshot for a listing. |
| MAP_VERSION | M04 Maps and Navigation | Core | One immutable published routing/map dataset covering a mall. |
| FLOOR_MAP | M04 Maps and Navigation | Core | Visual map assets and image-to-world transform for a floor/version. |
| NAVIGATION_NODE | M04 Maps and Navigation | Core | Graph vertex at a shop entrance, corridor junction, checkpoint, or connector. |
| VERTICAL_CONNECTOR | M04 Maps and Navigation | Core | Physical staircase, lift, or escalator shared across map versions. |
| CONNECTOR_STOP | M04 Maps and Navigation | Core | Graph location where a vertical connector serves a floor in one version. |
| NAVIGATION_EDGE | M04 Maps and Navigation | Core | A directed traversable connection; reverse travel needs a second edge. |
| QR_CHECKPOINT | M04 Maps and Navigation | Core | Stable printed QR destination, surviving map republishing. |
| CHECKPOINT_ANCHOR | M04 Maps and Navigation | Core | Version-specific pose and node attached to a stable QR checkpoint. |
| SHOP_ENTRANCE | M04 Maps and Navigation | Core | Connects a physical shop unit to a version-specific entrance node. |
| FACILITY_ANCHOR | M04 Maps and Navigation | Core | Connects a facility destination to a version-specific graph node. |
| AR_REFERENCE_ANCHOR | M04 Maps and Navigation | Prototype | Optional visual target or VPS reference aligned to the local map. |
| ROUTE_SESSION | M04 Maps and Navigation | Optional | Optional saved route request for troubleshooting or opt-in analytics. |
| ROUTE_STEP | M04 Maps and Navigation | Optional | Ordered graph path in a saved route session. |
| PURCHASE | M05 Purchase Verification | Core | Recorded sale used as evidence; not a complete checkout/payment system. |
| PURCHASE_ITEM | M05 Purchase Verification | Core | Immutable record of the product listing and amount bought. |
| VERIFICATION_TOKEN | M05 Purchase Verification | Core | One rotatable opaque review credential per purchase line. |
| TOKEN_REDEMPTION | M05 Purchase Verification | Core | Atomic, single successful claim of a purchase token by a user. |
| REVIEW | M06 Reviews and Moderation | Core | Shared review content with a concrete shop target and optional listing target. |
| REVIEW_VERIFICATION | M06 Reviews and Moderation | Core | Optional, unique binding between a product review and claimed purchase evidence. |
| REVIEW_REPLY | M06 Reviews and Moderation | Core | Public replies by authorized staff of the reviewed shop. |
| REVIEW_REPORT | M06 Reviews and Moderation | Core | A user report about one review. |
| MODERATION_ACTION | M06 Reviews and Moderation | Core | Append-only review moderation history. |
| USER_PREFERENCE | M07 Favourites and Preferences | Core | Optional one-to-one user settings record. |
| FAVOURITE_SHOP | M07 Favourites and Preferences | Core | A shop saved by a user. |
| FAVOURITE_PRODUCT | M07 Favourites and Preferences | Core | A shop listing saved by a user, preserving seller context. |
| CONVERSATION | M08 Chatbot and RAG | Prototype | A customer or administrator conversation in one mall context. |
| CHAT_MESSAGE | M08 Chatbot and RAG | Prototype | Ordered message in a conversation. |
| KNOWLEDGE_DOCUMENT | M08 Chatbot and RAG | Prototype | Controlled retrieval source with explicit mall and optional shop/listing scope. |
| DOCUMENT_REVISION | M08 Chatbot and RAG | Prototype | Immutable source content snapshot used to reproduce citations. |
| KNOWLEDGE_CHUNK | M08 Chatbot and RAG | Prototype | A retrieval-sized text segment from an immutable document revision. |
| CHUNK_EMBEDDING | M08 Chatbot and RAG | Optional | Optional external vector-index record for a chunk and embedding model. |
| MESSAGE_CITATION | M08 Chatbot and RAG | Prototype | Evidence chunks attached to an assistant answer. |
| MESSAGE_RECOMMENDATION | M08 Chatbot and RAG | Prototype | Ordered, structured product-listing recommendations in an answer. |
| METRIC_DEFINITION | M09 Analytics and Reports | Core | Meaning and formula version for each metric. |
| SHOP_METRIC_SNAPSHOT | M09 Analytics and Reports | Core | Frozen shop metric for a specific observation period and calculation cutoff. |
| REPORT_RUN | M09 Analytics and Reports | Core | One reproducible report request for a mall and period. |
| REPORT_SHOP_SCOPE | M09 Analytics and Reports | Core | Frozen list of shops included in a report run. |
| REPORT_METRIC_SNAPSHOT | M09 Analytics and Reports | Core | Exact immutable metric snapshots used in a report. |
| REPORT_ARTIFACT | M09 Analytics and Reports | Core | A generated downloadable output from a report run. |
| FORECAST_RUN | M09 Analytics and Reports | Prototype | Optional experimental regression run for one shop and metric. |
| FORECAST_TRAINING_SNAPSHOT | M09 Analytics and Reports | Prototype | Exact historical snapshots used to train a forecast run. |
| FORECAST_POINT | M09 Analytics and Reports | Prototype | Predicted value for one future period. |
| REPORT_FORECAST | M09 Analytics and Reports | Prototype | Optional forecast runs included in a generated report. |
