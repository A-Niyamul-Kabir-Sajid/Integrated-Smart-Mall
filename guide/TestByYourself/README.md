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

## Helpers

| Script | Run with | Does |
|---|---|---|
| [scripts/show-links.php](scripts/show-links.php) | `php artisan tinker guide/TestByYourself/scripts/show-links.php` | Prints every full URL and id (shops, workspaces, CSV import, listings, products, moderation desk, existing reviews). Read-only. |
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
