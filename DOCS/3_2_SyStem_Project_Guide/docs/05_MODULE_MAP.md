# 05 — Module Map

The older scaffold has **functional/product modules**, while the ERD package has **data-ownership modules**. They are intentionally not one-to-one. This map prevents agents from confusing UI ownership with database ownership.

| Canonical app module | Purpose | Existing functional source(s) | Existing ERD/data source |
|---|---|---|---|
| `accounts` | Customer Accounts & Preferences | `customer_accounts_preferences` | `01-accounts-access` |
| `directory` | Mall / Shop Directory | `mall_directory_product_discovery` | `02-mall-shop-directory` |
| `products` | Products & Catalogue | `mall_directory_product_discovery`, `shop_owner_operations` | `03-products-catalogue` |
| `navigation` | Indoor Maps & Navigation | `indoor_maps_navigation` | `04-maps-navigation` |
| `purchase-verification` | Purchase Verification | `shopping_purchase_assistance`, `reviews_ratings_trust`, `shop_owner_operations` | `05-purchase-verification` |
| `reviews` | Reviews, Ratings & Moderation | `reviews_ratings_trust` | `06-reviews-moderation` |
| `favourites` | Favourites & Preferences | `customer_accounts_preferences` | `07-favourites-preferences` |
| `chatbot` | Chatbot / RAG | `customer_chatbot_recommendations`, `mall_owner_analytics_decision_support` | `08-chatbot-rag` |
| `analytics` | Analytics & Reports | `shop_owner_analytics`, `mall_owner_analytics_decision_support` | `09-analytics-reports` |
| `shop-owner` | Shop Owner Operations | `shop_owner_operations`, `shop_owner_analytics` | `shared/other owners` |
| `mall-admin` | Mall Owner Administration | `mall_owner_administration` | `shared/other owners` |
| `platform` | Shared Platform Requirements | `shared_platform_requirements`, `parking_facilities_visitor_support` | `shared/other owners` |

## Rule
A feature may live in one application module while reading data owned by another. Cross-module writes must go through an application service/contract rather than modifying another module's internals directly.
