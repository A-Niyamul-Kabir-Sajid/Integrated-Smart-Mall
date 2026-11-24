# TestByYourself

Step-by-step manual checks you run yourself in the browser. **Every file stands on its own**: it repeats its own setup, creates the data and accounts it needs, and writes every URL in full, so you can run any one of them without reading another. Each file also names what must be refused and which automatic tests cover the same behaviour.

Every account password is `password`. The app runs at <http://127.0.0.1:8000>.

## Index

| File | Area | Epic |
|---|---|---|
| [00_prerequisites_and_demo_data](00_prerequisites_and_demo_data.md) | Reference: start the app, demo accounts and data, limits, reset | EPIC-00 |
| [01_smoke_and_platform](01_smoke_and_platform.md) | Health, security headers, language switch | EPIC-00, 01 |
| [02_accounts_and_sign_in](02_accounts_and_sign_in.md) | Register, sign in, sign out, suspended account | EPIC-02 |
| [03_password_and_profile](03_password_and_profile.md) | Profile edit, password change, password reset | EPIC-02 |
| [04_preferences_and_language](04_preferences_and_language.md) | Language, usual mall, route mode, notification switches | EPIC-02 |
| [05_mall_and_shop_directory](05_mall_and_shop_directory.md) | Mall home, shop search and filters, shop page and hours | EPIC-03 |
| [06_product_search_and_compare](06_product_search_and_compare.md) | Product search, offers, comparison | EPIC-04 |
| [07_shop_workspace_catalogue](07_shop_workspace_catalogue.md) | Shop staff manage listings, photos, shared details | EPIC-04 |
| [08_catalogue_csv_import](08_catalogue_csv_import.md) | CSV preview and all-or-nothing import (shop staff) | EPIC-04 |
| [09_issue_purchase_code_seller_side](09_issue_purchase_code_seller_side.md) | Record a sale and issue a one-time purchase code | EPIC-06 |
| [10_verified_reviews_customer](10_verified_reviews_customer.md) | Write reviews, Verified purchase badge, code reuse refused, shop review | EPIC-06, 07 |
| [11_shop_review_inbox_and_replies](11_shop_review_inbox_and_replies.md) | Shop reads reviews and replies publicly | EPIC-07 |
| [12_report_and_moderation](12_report_and_moderation.md) | Report a review, moderation desk decisions | EPIC-07 |
| [13_access_control_matrix](13_access_control_matrix.md) | Which role may open which page | EPIC-02 and all |
| [14_automated_tests](14_automated_tests.md) | Commands, test map, known gaps | all |
| [15_favourites_saved_items](15_favourites_saved_items.md) | Save shops and offers, Saved page, unavailable items | EPIC-08 |
| [16_shopping_lists](16_shopping_lists.md) | Shopping lists per mall, products and free text, tick off | EPIC-08 |
| [17_language_and_usual_mall](17_language_and_usual_mall.md) | Bangla error messages, usual mall first | EPIC-08 |
| [18_facility_finder_and_admin](18_facility_finder_and_admin.md) | Find facilities by type and floor; mall admins add and edit them | EPIC-09 |
| [19_saved_parking_and_occupancy](19_saved_parking_and_occupancy.md) | Save your parking spot, directions back, simulated free spaces, parking zones | EPIC-09 |
| [20_shop_onboarding_and_staff](20_shop_onboarding_and_staff.md) | Apply for a shop, mall approval or rejection, profile and hours, staff invitations and work functions, suspension | EPIC-10 |
| [21_stock_and_inventory](21_stock_and_inventory.md) | Track stock per listing, history, low-stock alerts, extra product options | EPIC-10 |
| [22_orders_sales_and_receipts](22_orders_sales_and_receipts.md) | Order and collect, accept and hand over, counter sales, review codes, receipts, voids | EPIC-10 |
| [23_returns_and_exchanges](23_returns_and_exchanges.md) | Request, approve, complete returns and exchanges, restock, refunded sales | EPIC-10 |
| [24_promotions_and_coupons](24_promotions_and_coupons.md) | Storewide offers, coupon codes, limits, discounts reaching the sale | EPIC-10 |
| [25_customer_support](25_customer_support.md) | Customer writes to a shop, shop answers, close, isolation | EPIC-10 |
| [26_maintenance_requests](26_maintenance_requests.md) | Shop reports a problem to the mall, mall works it, shop confirms | EPIC-10 |
| [27_mall_admin_tenants_and_floors](27_mall_admin_tenants_and_floors.md) | Mall office creates and edits shops, owners and units, manages floors, mall details, categories and reads the activity log | EPIC-11 |
| [28_announcements_and_events](28_announcements_and_events.md) | Mall notices and events: draft, publish, time windows, public pages | EPIC-11 |
| [29_complaints](29_complaints.md) | Customer complaints: file, assign, resolve or reject, confirm or reopen | EPIC-11 |
| [30_work_orders_and_maintenance_desk](30_work_orders_and_maintenance_desk.md) | Mall maintenance desk, work orders, queues on the admin home, moderation hand-off | EPIC-11 |
| [31_search_demand](31_search_demand.md) | Anonymous search counting, privacy masking, unmet-demand report | EPIC-14 |
| [32_page_views_and_funnel](32_page_views_and_funnel.md) | Shop and product views, staff exclusion, view-to-order funnel | EPIC-14 |
| [33_activity_rollups_retention_and_demo_data](33_activity_rollups_retention_and_demo_data.md) | Daily summaries, retention, labelled demonstration traffic | EPIC-14 |
| [34_sales_dashboards_and_metric_catalogue](34_sales_dashboards_and_metric_catalogue.md) | Shop and mall dashboards, quality flags, date filters, metric catalogue | EPIC-12 |
| [35_sales_forecast_prototype](35_sales_forecast_prototype.md) | Forecast with baselines, evaluation, run id and limits | EPIC-12 |
| [36_monthly_reports_and_exports](36_monthly_reports_and_exports.md) | Monthly reports, summaries from figures, CSV and print, visitor figures in exports | EPIC-12 |
| [37_assistant_knowledge_and_retrieval](37_assistant_knowledge_and_retrieval.md) | Assistant knowledge: written and captured documents, revisions, withdrawal, public versus internal retrieval | EPIC-13 |
| [38_customer_assistant](38_customer_assistant.md) | Visitors' assistant: grounded answers, product finder, directions, Bangla, Gemini key on and off | EPIC-13 |
| [39_admin_assistant_safety_and_evaluation](39_admin_assistant_safety_and_evaluation.md) | Administrators' assistant: figures, report drafts, refusals, activity page, audit trail, evaluation run | EPIC-13 |
| [40_blender_map_pipeline](40_blender_map_pipeline.md) | Blender map pipeline: prepare a LiDAR scan, draft the graph, place QR checkpoints and markers, refusals, export the map package | EPIC-05 |
| [41_map_service_and_api_keys](41_map_service_and_api_keys.md) | Separate map service: import a map, shop signs and advertisements in 3D, publish, API keys, the Smart Mall app reading with its key, revoking | EPIC-05 |
| [42_map_graph_checkpoints_and_routes](42_map_graph_checkpoints_and_routes.md) | Map service: navigation graph and refusals, one published map per mall, QR codes and their lifecycle, routes across floors, step-free, nearest facility, several stops, closures | EPIC-05 |
| [43_mall_map_embed_and_docs](43_mall_map_embed_and_docs.md) | The Map page and QR landing page in the Smart Mall app: embedded map, routes, Bangla, closures reaching an open map, signed links and allowed sites, `/docs` | EPIC-05 |
| [44_real_scan_pilot_map](44_real_scan_pilot_map.md) | The first real iPhone scan as the demo mall's ground floor: prepare, demo markers by script, export without warnings, import, 2D routes and Show on map, the scan in 3D | EPIC-05 |

## Helpers

| Script | Run with | Does |
|---|---|---|
| [scripts/show-links.php](scripts/show-links.php) | `php artisan tinker guide/TestByYourself/scripts/show-links.php` | Prints every full URL and id (shops, workspaces, CSV import, listings, products, moderation desk, assistant pages, existing reviews). Read-only. |
| [scripts/issue-purchase-code.php](scripts/issue-purchase-code.php) | `PRODUCT` and `OPTION` (or `LISTING_ID`) set, then `php artisan tinker guide/TestByYourself/scripts/issue-purchase-code.php` | Plays the seller: records a sale and prints a one-time purchase code, until the sales screen exists |

CSV test files: [samples/good.csv](samples/good.csv), [samples/bad.csv](samples/bad.csv), [samples/wrongheader.csv](samples/wrongheader.csv).

## Suggested order

- **Quick sanity check (15 min):** 02, 05, 06.
- **The verified-review story:** 10, then 11, 12 (each works alone).
- **Before a release:** everything, then 14.

## Rules for adding to this folder

1. Every implemented slice adds or updates a file here, in the same change as its automatic tests ([guide rule](../README.md)).
2. Copy [_TEMPLATE.md](_TEMPLATE.md). Name the file `NN_area_in_snake_case.md` with the next free number, and add it to the index above and to the test map in [14](14_automated_tests.md).
3. **A file must be self-contained.** Never write "do file NN first". Put the setup commands, the accounts, the data creation (extra accounts, purchase codes, reviews) and the id lookup inside the file itself. If a screen for a step does not exist yet, add or reuse a helper script in `scripts/` and call it from the file.
4. **Write every URL in full** (`http://127.0.0.1:8000/...`). For ids that change, tell the reader which line of `show-links.php` output to copy.
5. Every step states the account, the exact URL or button, the input and the expected result. Include what must be refused and what must not be saved.
6. Never write real purchase codes, passwords of real people or other secrets into these files. Demo accounts only.
7. When behaviour changes, change the steps. A guide that no longer matches the app is worse than none.
8. Record your run in the **Result** block (date, tester, pass or fail).
