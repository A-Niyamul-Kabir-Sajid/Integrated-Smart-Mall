# 3 2 SyStem — project handoff

Prepared: 2026-09-29, Asia/Dhaka.

## Source and confidence
This handoff uses conversation excerpts and project summaries supplied in the current ChatGPT session. It is not a full audit of all project conversations. Referenced PDFs, images, pasted files, repositories, and previous deliverables were not opened for this handoff. No source code was inspected. User decisions summarized below should be reconciled with newer instructions and actual repository evidence.

## Purpose
A university system-design project by Md. Shomik Shahriar, KUET CSE: browser-based indoor mall navigation combined with shop/product information, reviews, and mall management insights. Project name: 3 2 SyStem.

The intended audiences are customers, shop owners, and mall owners. The recent framing describes three interfaces sharing one backend; it does not require three native applications. The exact boundary of the shop-owner dashboard remains to be specified.

## Confirmed requests in available context
1. Browser-based access without installing a native customer app.
2. Custom mall maps, with QR codes at pillars/towers as checkpoints.
3. Scanning a QR opens the application with a known checkpoint, floor, and stored map location. A checkpoint can also store a reference direction; how the phone aligns to that direction needs implementation and validation.
4. Search shops and products, choose a destination, and compute a route using A* or Dijkstra.
5. A phone interface with about 30% dashboard and 70% AR navigation canvas, plus a button to hide AR. Responsive behavior remains to be designed.
6. WebAR navigation is a central requested feature.
7. Multi-floor navigation through stair/elevator connections.
8. Normal reviews and purchase-verified reviews. The user proposed a hash key combining a product key and mall internal key as proof of purchase; the secure protocol is not yet settled.
9. A small assistant/RAG feature for condition-based discovery, such as finding drinks and sorting matching products or stores by rating.
10. Monthly review reports and shop performance charts for mall management.
11. Prediction of upcoming shop performance using regression was requested; data availability and model scope are unresolved.
12. The user plans to explore iPhone 17 Pro Max LiDAR scanning for map input. The mapping/export workflow has not been validated here.

## Academic constraints and timeline
- Supervisor guidance in the summary: emphasize software and use basic Dijkstra or A* routing.
- Scope should connect to KUET CSE learning through third year, first semester.
- A nine-week progress roadmap has been requested. The user also stated an overall nine-month horizon. Do not equate these without confirming the milestone dates.
- Do not silently remove requested features because the initial milestone is short; distinguish staged prototypes from finished components.

## Proposed design, not a locked implementation
- Represent traversable corridors as a graph: nodes at checkpoints, junctions, shop entrances, stairs, and elevators; edges connect accessible paths.
- Each node has a floor and coordinates. Cross-floor edges represent vertical movement.
- Calculate route costs consistently; support restrictions such as blocked corridors or stair avoidance only when specified and represented in data.
- Keep a 2D route view available alongside the requested AR view.
- QR checkpoints can provide re-anchoring opportunities. Continuous tracking between them needs a separate approach and device tests.
- Candidate tools discussed previously: Blender, Three.js, WebXR, MindAR/AR.js, Node/Express, PostgreSQL/SQLite, and external VPS services such as Immersal or MultiSet. None is established here as the final stack, free deployment commitment, or verified cross-device solution.
- Mapshaper was explored as a possible map-editing foundation. Adding a routing system was discussed; no audited repository modification is established in this handoff.
- A secure purchase-verification design should consider server-issued unpredictable, single-use tokens bound to an actual transaction, with expiry and replay prevention. This is a recommendation replacing no user decision yet; a product identifier plus shared mall key alone should not be treated as proof of an individual purchase.

## Suggested data domains (to refine)
Mall, floor, map version, checkpoint, route node, route edge, vertical connector, shop, product, user/role, purchase or verification record, review, report, and analytics event. These are conceptual domains, not an existing database schema.

## Desired user journey
Scan checkpoint QR → open browser dashboard → establish starting checkpoint → search shop/product → choose destination → compute multi-floor route → show route and AR guidance where supported → re-anchor as needed → arrive → optionally submit a normal or purchase-verified review.

## Documentation requested by the user
Maintain a master PRD, system analysis report, and nine-week progress roadmap. The analysis/report structure requested is:
1. Introduction
2. Problems
3. Solution and key decisions
4. Stakeholders, use cases, and scope
5. Functional requirements
6. Non-functional requirements and quality targets
7. Product workflow and evidence/experience (exact teacher wording should be checked)
8. System architecture
9. Core algorithm
10. Data models and lifecycle
11. Security and privacy
12. Conclusion

The handoff does not claim that these deliverables already exist or replace their current versions.

## Open decisions and evidence needed
- Actual repository, existing implementation, chosen stack, and team roles.
- Target browsers and phones, especially the required iOS/Android coverage.
- AR localization method, camera-to-map alignment, tracking behavior, and relocalization measurements.
- LiDAR export format and conversion into cleaned floor geometry plus routing graph.
- Prototype venue, number of floors, checkpoint placement, and available map permissions.
- Complete feature list and explicit full-implementation versus prototype classification.
- Shop-owner permissions, product maintenance, and how purchases generate verification tokens.
- Authentic review/sales data, metric definitions, regression target, and evaluation baseline.
- RAG data sources, retrieval approach, model access/cost, and protection against unsupported answers.
- Measurable performance, accessibility, security, and usability acceptance criteria.
- Confirmed academic deadlines and contents of the latest supervisor form/images.
