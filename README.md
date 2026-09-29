# TPD Tool - Travel Partner Directory Enterprise Plugin

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B%20%7C%208.x-purple.svg)](https://php.net)
[![Elementor](https://img.shields.io/badge/Elementor-Compatible-pink.svg)](https://elementor.com)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

**TPD Tool** is an enterprise-grade WordPress solution built for the **Travel Partner Directory by TARC** marketplace. It connects professional Travel Advisors, luxury and boutique Travel Suppliers (cruise lines, tour operators, resort collections), and everyday travelers through two tailored frontend portals and a centralized Super Admin Control Hub.

---

## 🚀 Key Features

### 1. Dual Specialized User Dashboards
* **Travel Advisor Dashboard (`/advisor-dashboard/`):**
  * Pixel-perfect layout inspired by the Steven Gould TARC layout.
  * Dark navy sidebar (`#0b1526`), scenic European coastal landscape welcome hero banner.
  * 3-Card Analytics Strip: Profile Views, Message Threads, and Client Trip Inquiries with 30-day comparative delta indicators (`+14% vs last mo`).
  * "Find Your Next Supplier" quick-search and filter bar by Destination & Travel Style.
  * Featured Supplier Grid showcasing active "Reps Online" indicators and 1-click Contact/Meet actions.
  * 6-Tile Quick Actions hub (Compare Cruise Lines, Schedule Meeting, Submit RFQ, Client Trip Inquiries, The TARC Connect, Access Resources).
  * Bookmarked Saved Suppliers list and Upcoming Webinars schedule.

* **Supplier Partner Portal (`/supplier-dashboard/`):**
  * Tailored management hub inspired by Sarah Mitchell – AmaWaterways layout.
  * 6-Metric KPI Ribbon: Profile Views (2,847), Message Threads (142), Meeting Requests (38), Quote Requests (64), Listing Saves (189), Bookings Reported ($1.2M).
  * Virtual Office showcasing Regional Sales Directors & BDMs with live presence indicators and direct advisor chat links.
  * "Manage Your Content" 8-tile action center.
  * Recent Conversations inbox with unread status indicators.
  * 85% Profile Completion tracker with interactive checklist and quick-update shortcuts.
  * Upcoming Office Hours and live Q&A event scheduler.

### 2. Free vs. Paid Membership Tier Gating
* **Built-in Tiering (`free` vs. `paid`):**
  * Super Admin can assign or toggle any user between Free and Paid Pro tiers with 1 click.
  * Pro Feature Teaser card with upgrade prompt rendered automatically when Free tier users attempt to access gated tools.
  * Centralized **Feature Permission Matrix** in the Super Admin hub allows platform owners to dynamically check/uncheck which capabilities belong to Free vs. Paid tiers.

### 3. Real-Time Chat & Instant Email Alert Pipeline
* In-app live chat messenger with threaded conversation keys (`conv_{user1}_{user2}`).
* **Guest Traveler Mode:** Unauthenticated travelers can send inquiries directly to advisors or suppliers by entering their name and email.
* **Instant Email Notifications:** Every message immediately dispatches a formatted alert via `wp_mail` to the recipient's inbox with a 1-click reply link and Reply-To header configured.

### 4. Dedicated Super Admin Control Hub (`/tpd-admin/` or WP Admin Menu)
* Platform-wide KPI overview: Total Advisors, Total Suppliers, Paid vs. Free breakdown, and Cumulative Inquiries/Messages.
* User Directory table with live 1-click Free/Paid status toggles.
* Interactive Feature Permission Matrix for both Travel Advisors and Suppliers.

### 5. Shortcodes & Elementor Custom Widgets
All dashboard views and tools are available via both WordPress Shortcodes and native Elementor widgets:
* `[tpd_advisor_dashboard]` / Elementor **TPD Advisor Dashboard**
* `[tpd_supplier_dashboard]` / Elementor **TPD Supplier Dashboard**
* `[tpd_admin_dashboard]` / Elementor **TPD Super Admin Hub**
* `[tpd_chat_inbox]` / Elementor **TPD Chat Messenger**
* `[tpd_saved_suppliers]` / Elementor **TPD Saved Suppliers**
* `[tpd_events_list]` / Elementor **TPD Events & Webinars**

---

## 📁 Architecture & File Structure

```text
wp-content/plugins/tpd-tool/
├── tpd-tool.php                     # Plugin bootstrap & constants
├── assets/
│   ├── css/
│   │   ├── tpd-tool.css             # Main portal CSS (navy theme, cards, metrics, layouts)
│   │   └── tpd-chat.css             # Chat bubbles, message stream, composer
│   └── js/
│       ├── tpd-dashboard.js         # Tab switching, search filtering, bookmarking
│       ├── tpd-chat.js              # Real-time chat dispatch & stream updates
│       └── tpd-admin.js             # 1-click tier switcher & permission matrix AJAX
├── includes/
│   ├── class-tpd-core.php           # Asset enqueuing, rewrite routing, admin bar control
│   ├── class-tpd-roles.php          # User roles (travel_advisor, supplier)
│   ├── class-tpd-tiers.php          # Free vs Paid logic, permissions matrix & lock cards
│   ├── class-tpd-cpt.php            # Custom Post Types & Taxonomies
│   ├── class-tpd-analytics.php      # 30-day comparative analytics engine
│   ├── class-tpd-chat.php           # Messaging backend & email notification dispatch
│   ├── class-tpd-favorites.php      # Saved/Bookmarked suppliers manager
│   ├── class-tpd-events.php         # Events & Webinars manager
│   ├── class-tpd-shortcodes.php     # Shortcode registry
│   ├── class-tpd-elementor.php      # Elementor category & widget registration
│   └── class-tpd-seeder.php         # Demo content seeder
├── templates/
│   ├── advisor/
│   │   └── dashboard.php            # Advisor Portal view (Design 1)
│   ├── supplier/
│   │   └── dashboard.php            # Supplier Portal view (Design 2)
│   ├── admin/
│   │   └── super-admin-dashboard.php # Super Admin Control Hub
│   └── shared/
│       └── chat-box.php             # Embedded live chatbox & composer
└── widgets/
    └── elementor/
        ├── widget-advisor-dashboard.php
        ├── widget-supplier-dashboard.php
        ├── widget-admin-dashboard.php
        └── widget-chat-inbox.php
```

---

## 🛠 Installation & Setup

1. Copy or clone this repository into your WordPress `wp-content/plugins/` directory:
   ```bash
   cd wp-content/plugins
   git clone https://github.com/Idris8004/TPD-tool.git tpd-tool
   ```
2. Activate the plugin via WP Admin (`Plugins -> Installed Plugins`) or via WP-CLI:
   ```bash
   wp plugin activate tpd-tool
   ```
3. To seed demo entities matching the design mockups (Steven Gould, Sarah Mitchell – AmaWaterways, Four Seasons, Viking, Abercrombie & Kent, Webinars), run:
   ```bash
   wp tpd-tool-seed
   ```
   *(Or deactivate and re-activate the plugin once).*

---

## 🔗 Default URLs

| Portal | URL Path | Role / Access |
|---|---|---|
| **Travel Advisor Portal** | `/advisor-dashboard/` | Travel Advisors & Admins |
| **Supplier Partner Portal** | `/supplier-dashboard/` | Suppliers & Admins |
| **Super Admin Hub** | `/tpd-admin/` or `WP Admin -> TPD Control Hub` | Administrators (`manage_options`) |

---

## 📄 License
GPLv2 or later. Developed for Travel Partner Directory by TARC.
