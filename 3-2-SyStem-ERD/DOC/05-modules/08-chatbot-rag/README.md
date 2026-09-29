# Module 08 — Chatbot and RAG

Conversations, versioned knowledge, retrieval citations, and product recommendations.

Status: proposed logical design v1.0; not an implemented or supervisor-approved database.

## Files

- [Full Mermaid source](ERD.mmd) and [Markdown rendering](ERD.md).
- [Data dictionary](DATA-DICTIONARY.md): all attributes, keys, nullability, references, and indexes.
- [Business rules](BUSINESS-RULES.md): constraints and lifecycle decisions.
- `ERD.svg`: ready-to-open vector preview.
- `views/`: smaller diagrams for detailed reading where supplied.

## Owned tables

| Table | Scope | Purpose |
| --- | --- | --- |
| CONVERSATION | Prototype | A customer or administrator conversation in one mall context. |
| CHAT_MESSAGE | Prototype | Ordered message in a conversation. |
| KNOWLEDGE_DOCUMENT | Prototype | Controlled retrieval source with explicit mall and optional shop/listing scope. |
| DOCUMENT_REVISION | Prototype | Immutable source content snapshot used to reproduce citations. |
| KNOWLEDGE_CHUNK | Prototype | A retrieval-sized text segment from an immutable document revision. |
| CHUNK_EMBEDDING | Optional | Optional external vector-index record for a chunk and embedding model. |
| MESSAGE_CITATION | Prototype | Evidence chunks attached to an assistant answer. |
| MESSAGE_RECOMMENDATION | Prototype | Ordered, structured product-listing recommendations in an answer. |

## External references

| Referenced table | Owner |
| --- | --- |
| APP_USER | Accounts and Access |
| MALL | Mall and Shop Directory |
| SHOP | Mall and Shop Directory |
| SHOP_PRODUCT | Products and Catalogue |

## Design explanation

The assistant has two complementary inputs: current structured catalogue/review queries and versioned retrieval sources. For a request such as drinks sorted by rating, obtain real listing IDs, availability, and explicitly defined rating aggregates from SQL first. Use retrieved text to explain results; do not invent products or treat generated prose as the catalogue.

KNOWLEDGE_DOCUMENT identifies the source and access boundary. DOCUMENT_REVISION freezes the source content; KNOWLEDGE_CHUNK supplies retrieval segments. MESSAGE_CITATION keeps the exact chunks that supported an answer. CHUNK_EMBEDDING is an optional pointer to an external vector index. A keyword-search prototype may omit it.

Filter retrieval by mall and user authorization before generation. Admin-only reports cannot enter public conversation context. Validate suggested listing IDs before saving MESSAGE_RECOMMENDATION. Citation eligibility and authorization are checked at retrieval and again on display. When source data changes, ingest a new revision and exclude obsolete revisions from new retrieval while preserving prior citations within retention rules.

This schema supports storing an implementation; it does not establish answer accuracy. Retain a small evaluation set for factual answers, empty results, stale catalogue data, wrong-mall retrieval, and access violations.

## Update rule

Update this module, its cross-module contract, and the master diagram in the same change. Existing parent tables are referenced, never copied into this module. All current proposals and unresolved choices are listed in the shared design notes.
