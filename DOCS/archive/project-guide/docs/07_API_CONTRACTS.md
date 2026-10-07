# 07 — API & Module Contracts

Use this file for interfaces between application modules and for external integration boundaries.

## Contract rules

- Module A should call Module B through a documented service/contract, not query or mutate B's internals ad hoc.
- Contracts define inputs, outputs, authorization, errors and events.
- External services sit behind adapters; secrets/provider specifics stay outside controllers.

## Initial cross-module flows to document

Navigation destination lookup; purchase -> verification token -> verified review; product/shop discovery -> chatbot recommendation; operational transactions -> analytics/reporting; admin map edits -> navigation graph versions.
