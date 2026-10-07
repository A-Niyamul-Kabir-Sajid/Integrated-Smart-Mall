For your **3 2 SyStem**, you can build a **mall platform with three connected interfaces: customer app, shop-owner dashboard, and mall-owner dashboard**. A shared backend connects navigation, shopping, shop operations, and mall management.

The list below is a **proposed full feature catalogue**. It includes your existing ideas—indoor navigation, WebAR, verified reviews, chatbot, reports, and forecasting—plus additional modules you could choose to implement.

### 1. Customer accounts and preferences

- **Registration and login:** Email or phone-based accounts.
- **Guest access:** Allow visitors to browse shops and use navigation without registering.
- **Customer profile:** Manage personal information, preferences, and saved items.
- **Favourites:** Save shops, products, restaurants, and offers.
- **Visit history:** View previously visited or searched shops, with customer consent.
- **Accessibility preferences:** Prefer elevators, wheelchair-accessible routes, and accessible facilities.
- **Language selection:** Bangla and English interfaces.
- **Notification preferences:** Choose which offers and updates to receive.

### 2. Mall directory and product discovery

- **Shop directory:** List all shops by floor and category.
- **Shop profiles:** Show descriptions, opening hours, contact details, location, photos, and ratings.
- **Product search:** Find which shops sell a particular product.
- **Filters and sorting:** Filter by category, price, brand, rating, availability, and floor.
- **Product details:** Display descriptions, images, variants, prices, and availability.
- **Product comparison:** Compare similar products from different shops.
- **Service directory:** Find salons, repair centres, banking services, entertainment, and other services.
- **Facility search:** Locate washrooms, prayer rooms, ATMs, information desks, elevators, and exits.
- **Opening status:** Show whether shops and facilities are currently open.
- **Offers directory:** Browse active discounts and promotions.

### 3. Indoor maps and navigation

These features form the core of your existing project.

- **Custom floor maps:** Display the mall’s layout, shops, corridors, and facilities.
- **QR starting location:** Scan a pillar or checkpoint QR code to identify the starting position.
- **Destination selection:** Choose a shop, product’s shop, or facility as the destination.
- **Shortest-path routing:** Calculate routes using A* or Dijkstra.
- **Multi-floor routing:** Connect floors through stairs, escalators, and elevators.
- **Accessible routing:** Offer routes that avoid stairs where suitable paths exist.
- **Map-based guidance:** Display the route on a 2D or 3D map.
- **WebAR guidance:** Show directional guidance through the camera view, subject to the positioning and tracking approach you validate.
- **AR visibility toggle:** Switch between navigation and the shopping dashboard.
- **Checkpoint re-positioning:** Scan another QR code to update the known location.
- **Route recalculation:** Recalculate after a location update, destination change, or corridor closure.
- **Multi-stop journeys:** Plan visits to several shops.
- **Distance and estimated walking time:** Show route length and approximate travel time.
- **Temporary restrictions:** Avoid closed corridors or unavailable connectors.
- **Shareable destinations:** Share a shop or meeting-point link with another visitor.

**Important design distinction:** A QR scan establishes a known checkpoint. Continuously updating the customer’s position while walking requires a separate tracking solution.

### 4. Shopping and purchase assistance

- **Shopping lists:** Create a list of products to find.
- **Shop suggestions for a list:** Identify shops that carry the requested items.
- **Availability enquiries:** Ask a shop whether an item is available.
- **Reservations:** Reserve a product for an agreed period.
- **Click-and-collect orders:** Place an order and collect it at the shop.
- **Order tracking:** View confirmation, preparation, and collection status.
- **Digital receipts:** Keep purchase records in the account.
- **Return and exchange requests:** Submit requests according to each shop’s policy.
- **Coupons:** Save and redeem eligible offers.
- **Loyalty rewards:** Earn shop-specific or mall-wide points.
- **Payments:** Optionally support online payment and refunds.
- **Customer–shop messaging:** Communicate about products, reservations, or orders.

A shared cart containing products from multiple shops is a further extension: it also needs separate fulfilment, payment allocation, and refund handling for each shop.

### 5. Reviews, ratings, and trust

- **Normal reviews:** Let customers review shops without purchase verification.
- **Purchase-verified reviews:** Mark reviews linked to a valid purchase.
- **Product and shop ratings:** Keep product quality and shop service feedback distinguishable.
- **Rating categories:** Rate service, value, cleanliness, and other relevant aspects.
- **Receipt QR or verification token:** Issue a purchase-linked credential that customers can redeem.
- **Duplicate prevention:** Limit repeated verification or reviews for the same eligible purchase.
- **Shop responses:** Allow shop owners to respond to feedback.
- **Review reporting:** Let users flag spam, abuse, or suspicious reviews.
- **Moderation:** Provide a review queue and recorded moderation decisions.
- **Rating breakdown:** Show rating distribution and distinguish verified from unverified feedback.

For your hash-key idea, **verification should rely on a server-validated purchase record and an unguessable or securely signed token**. Combining a product ID with a mall key alone does not establish that a purchase happened.

### 6. Customer chatbot and recommendations

- **Natural-language search:** Handle questions such as “Where can I get a cold drink?”
- **Condition-based suggestions:** Filter suggestions by budget, category, rating, floor, and availability.
- **Shop and product recommendations:** Recommend relevant options using maintained mall data.
- **Navigation handoff:** Start directions to a recommended shop.
- **Mall FAQs:** Answer questions about opening hours, parking, facilities, and policies.
- **Offer discovery:** Find promotions matching a customer’s request.
- **Comparison assistance:** Explain differences between available options.
- **Source-linked answers:** Link answers to the relevant shop, product, or policy.
- **Human assistance:** Direct unresolved questions to the information desk.

Your proposed **RAG assistant** fits here. Live facts such as stock and prices should come from current database queries.

### 7. Parking, facilities, and visitor support

- **Parking information:** Show parking entrances, zones, charges, and operating hours.
- **Saved parking location:** Record a parking zone or scan a parking checkpoint.
- **Return-to-car navigation:** Route customers back to their saved parking location.
- **Parking availability:** Display occupancy if a reliable source supplies it.
- **Event calendar:** Show exhibitions, sales events, and entertainment.
- **Event registration:** Support booking where required.
- **Lost and found:** Submit reports and track claims.
- **Help requests:** Contact the information desk or request assistance.
- **Facility complaints:** Report broken elevators, cleanliness issues, or other problems.
- **Emergency information:** Display approved contacts, exits, and safety instructions.

### 8. Shop-owner operations

- **Shop onboarding:** Apply to join the platform and submit shop details.
- **Staff accounts:** Assign permissions to managers, sales staff, and catalogue editors.
- **Profile management:** Update opening hours, contacts, branding, and descriptions.
- **Catalogue management:** Add and edit products, variants, prices, and images.
- **Bulk catalogue import:** Upload many products at once.
- **Inventory management:** Maintain stock quantities and availability.
- **Low-stock alerts:** Identify products needing replenishment.
- **Order and reservation management:** Accept, reject, prepare, and complete requests.
- **Purchase registration:** Record purchases and issue verification credentials.
- **Receipt management:** Generate and retrieve digital receipts.
- **Returns and refunds:** Track requests and their outcomes.
- **Promotion management:** Create offers, coupons, and campaigns.
- **Customer support:** Respond to messages, complaints, and reviews.
- **Mall service requests:** Report maintenance or operational issues to mall management.
- **Mall notices and dues:** View announcements, rent, and service-charge records if these modules are included.

### 9. Shop-owner analytics

- **Sales dashboard:** Show recorded revenue, orders, and average order value.
- **Product performance:** Identify popular, slow-moving, and frequently searched products.
- **Inventory reports:** Summarise stock levels and stock-outs.
- **Discovery analytics:** Track profile views, product views, and navigation requests.
- **Customer feedback trends:** Monitor ratings and recurring complaints.
- **Campaign performance:** Measure coupon use and purchases attributable to promotions where possible.
- **Sales forecasting:** Estimate future sales when sufficient historical data exists.
- **Report export:** Download periodic reports.

### 10. Mall-owner administration

- **Shop approval and management:** Approve tenants and maintain their platform records.
- **Mall structure management:** Manage buildings, floors, units, and categories.
- **Map editor:** Update shop locations, walkable paths, and facilities.
- **Navigation graph management:** Maintain nodes, edges, distances, and accessibility attributes.
- **QR checkpoint management:** Generate, assign, replace, and disable checkpoint codes.
- **Closures and restrictions:** Record unavailable corridors, shops, or facilities.
- **Role and permission management:** Control access for administrators and operational staff.
- **Content moderation:** Review reported products, promotions, and reviews.
- **Announcements:** Publish mall-wide notices and events.
- **Complaint management:** Assign cases, track progress, and record resolutions.
- **Maintenance management:** Create work orders, assign staff, and track completion.
- **Tenant administration:** Optionally manage leases, rent, service charges, and renewal dates.
- **Advertising management:** Manage promoted placements and clearly label sponsored content.
- **Audit history:** Record important administrative changes.

### 11. Mall-owner analytics and decision support

This module supports your idea of helping the owner understand shop performance.

- **Mall overview:** Summarise participating shops, platform activity, complaints, and ratings.
- **Shop performance comparison:** Compare shops using clearly defined metrics.
- **Category and floor analysis:** Compare demand across categories and locations.
- **Search-demand analysis:** Identify products visitors search for but cannot find.
- **Navigation-demand analysis:** Show frequently requested destinations and routes.
- **Review analysis:** Identify recurring praise and complaints.
- **Service-quality flags:** Highlight repeated unresolved complaints or declining ratings for investigation.
- **Monthly reports:** Generate summaries with charts, findings, and supporting data.
- **Performance forecasting:** Use regression or another appropriate method to estimate a defined outcome.
- **Admin assistant:** Answer questions such as “Which shops received the most cleanliness complaints this month?”
- **Data exports:** Export authorised data for further analysis.

**Keep measurements honest:** Navigation requests measure interest, not confirmed visits. Sales analysis needs purchase data, and footfall analysis needs an actual visitor-counting source.

### 12. Shared platform requirements

- **Role-based access:** Separate customer, shop staff, mall administrator, and support permissions.
- **Data isolation:** Ensure each shop can access only its authorised business data.
- **Security:** Protect accounts, purchase credentials, payments, and administrative actions.
- **Privacy controls:** Limit collection, define retention periods, and support account deletion.
- **Notifications:** Deliver relevant order, offer, complaint, and maintenance updates.
- **Audit logs:** Track sensitive changes and actions.
- **Backup and recovery:** Protect maps, catalogues, and operational records.
- **Accessible interface:** Support readable text, keyboard use, and assistive technologies.
- **Mobile-friendly browser access:** Fit the browser-first approach you already want.
- **Poor-connectivity handling:** Cache suitable map and directory content and indicate stale information.
- **Integrations:** Connect external POS, inventory, payment, or parking systems where needed.

### What to prioritise for your university project

Treat the catalogue above as the long-term product scope. For a coherent first version, prioritise:

| Priority | Features |
|---|---|
| **Core foundation** | Three user roles, shop/product directory, catalogue management, custom maps, QR checkpoints, A*/Dijkstra routing |
| **Main differentiators** | WebAR feasibility prototype, normal and purchase-verified reviews, customer chatbot, monthly mall reports |
| **Next expansion** | Multi-floor and accessible routing, complaints, promotions, shop analytics, regression-based forecasting |
| **Later business modules** | Orders, payments, full inventory/POS integration, loyalty, parking occupancy, leases and rent |

**Start validating WebAR early**, alongside the basic map and routing system, because it is central to your intended customer experience and has the largest unresolved technical dependency.
