# 10 — Build Order

1. Platform foundation: Laravel project, DB, auth/RBAC, module conventions.
2. Directory + products.
3. Custom maps/checkpoints + graph data.
4. A*/Dijkstra routing + multi-floor navigation + 2D navigation UI.
5. Shop-owner operations needed for purchases/catalogue.
6. Purchase verification + reviews/ratings/moderation.
7. Mall-owner administration.
8. Analytics/monthly reports.
9. Customer chatbot/RAG on approved data.
10. WebAR/provider adapters and prototype evaluation.
11. Forecasting/external integrations only after their data/contracts are ready.

Do not start dependent modules by bypassing unfinished contracts; use test doubles or clearly marked adapters.
