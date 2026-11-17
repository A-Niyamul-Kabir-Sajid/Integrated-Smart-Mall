# ChatbotRag

Grounded customer assistant, retrieval over approved sources and the authorized admin assistant.

- Namespace: `App\Modules\ChatbotRag`
- Jira: EPIC-13 Chatbot / RAG
- Requirements, business rules and ERD: [DOCS/modules/ChatbotRag/README.md](../../../DOCS/modules/ChatbotRag/README.md)
- Folder layout and coupling rules: [app/Modules/README.md](../README.md)

## Status

Implemented for EPIC-13 (decision D-018); tables, routes, commands, evaluation result and limits are in [DOCS/modules/ChatbotRag/README.md](../../../DOCS/modules/ChatbotRag/README.md), progress in [DOCS/PROGRESS.md](../../../DOCS/PROGRESS.md).

| Folder | Holds |
|---|---|
| `Services/Knowledge` | Parsing, chunking, documents and revisions, capture of mall records |
| `Services/Retrieval` | `RetrievableChunks` (the one access filter), PostgreSQL and portable search, query expansion, `KnowledgeRetriever` |
| `Services/Generation` | Template driver, answer check, fallback; the Gemini driver is in `Adapters/` |
| `Services/Assistant` | Input guard, tool router, tools, conversations, directions, interaction log |
| `Services/Evaluation` | The evaluation cases and runner |

Rules that must hold: every chunk read goes through `RetrievableChunks::query()`; the scope of a conversation comes from the route and the account, never from the message; only `public` passages reach `GeminiAnswerGenerator`; nothing logs the API key, a prompt or an answer.
