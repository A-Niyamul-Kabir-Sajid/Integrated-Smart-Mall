# Chatbot and RAG — business rules

These rules are part of the design. An ER diagram alone does not enforce them. Use [constraint enforcement](../../03-database/CONSTRAINT-ENFORCEMENT.md) to distinguish database constraints from transactional/application checks.

## CONVERSATION

- An admin conversation requires an authenticated mall-authorized user. An anonymous conversation is public only.
- Read access to anonymous conversations requires a server-issued session credential outside this logical content schema. IDs alone are not authorization.

## CHAT_MESSAGE

- Unique tuple: `(conversation_id, sequence_no)`.
- sequence_no >= 0. Do not expose hidden system instructions or secrets through user-facing history.

## KNOWLEDGE_DOCUMENT

- If listing is present, shop_id is present and matches the listing; shop mall matches mall_id.
- mall_guide/faq use neither shop nor listing; shop_profile uses shop only; product_listing uses both. admin_report is mall-scoped in this baseline.
- Retrieval must filter by mall and access scope before sending context to a model.

## DOCUMENT_REVISION

- Unique tuple: `(document_id, revision_no)`.
- Apply declared keys, foreign keys, nullability, and field meanings.

## KNOWLEDGE_CHUNK

- Unique tuple: `(revision_id, chunk_no)`.
- Apply declared keys, foreign keys, nullability, and field meanings.

## CHUNK_EMBEDDING

- Composite primary key (chunk_id, model_key). dimensions > 0.
- A search-only prototype can omit this table. A relational vector extension is an alternative physical implementation, not a second required store.

## MESSAGE_CITATION

- Unique tuple: `(message_id, citation_no)`.
- Message role is assistant. Citation source mall/access scope must be permitted by the conversation and current user authorization.
- Retain referenced revisions or an approved redacted evidence record for the conversation retention period.

## MESSAGE_RECOMMENDATION

- Unique tuple: `(message_id, rank_no)`.
- Listing belongs to conversation mall. Refresh availability/price from catalogue at display time.
- Validate model-proposed IDs and ranking against database results; generated text must not create fictional inventory.
