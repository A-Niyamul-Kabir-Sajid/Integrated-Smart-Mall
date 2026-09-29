# Chatbot and RAG — data dictionary

All fields are required unless Nullable is Yes. Types are logical; see [shared conventions](../../03-database/CONVENTIONS.md) before generating SQL. PK, FK, and UK mean primary key, foreign key, and single-column unique key. Multiple PK fields form one composite primary key.

## CONVERSATION

A customer or administrator conversation in one mall context.

Scope: **Prototype**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| conversation_id | uuid | PK | No | — | Conversation identifier. |
| mall_id | uuid | FK | No | MALL.mall_id | Current mall context. |
| user_id | uuid | FK | Yes | APP_USER.user_id | Signed-in owner, or null for an anonymous session. |
| access_scope | varchar(16) | — | No | — | public or mall_admin. |
| created_at | timestamptz | — | No | — | Start time. |
| expires_at | timestamptz | — | No | — | Retention expiry. |

Primary key: `(conversation_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## CHAT_MESSAGE

Ordered message in a conversation.

Scope: **Prototype**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| message_id | uuid | PK | No | — | Message identifier. |
| conversation_id | uuid | FK | No | CONVERSATION.conversation_id | Parent conversation. |
| sequence_no | integer | — | No | — | Message order within the conversation. |
| role | varchar(16) | — | No | — | user, assistant, or system. |
| content | text | — | No | — | Message body. |
| model_label | varchar(120) | — | Yes | — | Model/version used for generated output. |
| created_at | timestamptz | — | No | — | Message time. |

Primary key: `(message_id)`.

Additional unique keys: `(conversation_id, sequence_no)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## KNOWLEDGE_DOCUMENT

Controlled retrieval source with explicit mall and optional shop/listing scope.

Scope: **Prototype**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| document_id | uuid | PK | No | — | Source document identifier. |
| mall_id | uuid | FK | No | MALL.mall_id | Owning mall. |
| shop_id | uuid | FK | Yes | SHOP.shop_id | Optional shop source. |
| shop_product_id | uuid | FK | Yes | SHOP_PRODUCT.shop_product_id | Optional specific product listing source. |
| source_kind | varchar(24) | — | No | — | mall_guide, shop_profile, product_listing, faq, or admin_report. |
| title | varchar(200) | — | No | — | Source title. |
| source_locator | text | — | No | — | Controlled source URL or internal locator. |
| access_scope | varchar(16) | — | No | — | public or mall_admin. |
| status | varchar(16) | — | No | — | active or withdrawn. |

Primary key: `(document_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## DOCUMENT_REVISION

Immutable source content snapshot used to reproduce citations.

Scope: **Prototype**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| revision_id | uuid | PK | No | — | Revision identifier. |
| document_id | uuid | FK | No | KNOWLEDGE_DOCUMENT.document_id | Source document. |
| revision_no | integer | — | No | — | Source revision sequence. |
| content_hash | varchar(64) | — | No | — | SHA-256 of captured text. |
| content_text | text | — | No | — | Captured trusted content. |
| source_updated_at | timestamptz | — | No | — | Timestamp of the underlying source revision. |
| captured_at | timestamptz | — | No | — | Ingestion time. |

Primary key: `(revision_id)`.

Additional unique keys: `(document_id, revision_no)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## KNOWLEDGE_CHUNK

A retrieval-sized text segment from an immutable document revision.

Scope: **Prototype**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| chunk_id | uuid | PK | No | — | Chunk identifier. |
| revision_id | uuid | FK | No | DOCUMENT_REVISION.revision_id | Source revision. |
| chunk_no | integer | — | No | — | Order within revision. |
| chunk_text | text | — | No | — | Text used for retrieval. |

Primary key: `(chunk_id)`.

Additional unique keys: `(revision_id, chunk_no)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## CHUNK_EMBEDDING

Optional external vector-index record for a chunk and embedding model.

Scope: **Optional**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| chunk_id | uuid | PK, FK | No | KNOWLEDGE_CHUNK.chunk_id | Embedded chunk. |
| model_key | varchar(120) | PK | No | — | Embedding model and version. |
| dimensions | integer | — | No | — | Vector dimensionality. |
| index_record_key | varchar(200) | UK | No | — | External index namespace/key; vector bytes live in that index. |
| created_at | timestamptz | — | No | — | Indexing time. |

Primary key: `(chunk_id, model_key)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## MESSAGE_CITATION

Evidence chunks attached to an assistant answer.

Scope: **Prototype**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| message_id | uuid | PK, FK | No | CHAT_MESSAGE.message_id | Answer containing the citation. |
| chunk_id | uuid | PK, FK | No | KNOWLEDGE_CHUNK.chunk_id | Retrieved supporting chunk. |
| citation_no | integer | — | No | — | Citation position in the answer. |
| retrieval_score | decimal | — | Yes | — | Score whose interpretation depends on the retriever. |
| quoted_excerpt | text | — | Yes | — | Optional short supporting excerpt. |

Primary key: `(message_id, chunk_id)`.

Additional unique keys: `(message_id, citation_no)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## MESSAGE_RECOMMENDATION

Ordered, structured product-listing recommendations in an answer.

Scope: **Prototype**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| message_id | uuid | PK, FK | No | CHAT_MESSAGE.message_id | Assistant answer. |
| shop_product_id | uuid | PK, FK | No | SHOP_PRODUCT.shop_product_id | Recommended listing; shop is derived from this listing. |
| rank_no | integer | — | No | — | Display order. |
| reason | text | — | No | — | Short explanation grounded in source data. |

Primary key: `(message_id, shop_product_id)`.

Additional unique keys: `(message_id, rank_no)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.
