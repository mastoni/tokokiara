# Project Requirements Document (PRD)

## 1. Project Overview
TokoKiara is a full-stack web application designed to serve as a Point-of-Sale (POS) and inventory management system for small to medium retail businesses. It combines sales processing, real-time stock management, user authentication, reporting, and configuration tools in one unified platform. By streamlining these core operations, TokoKiara helps merchants reduce manual errors, speed up checkout workflows, and maintain accurate inventory counts without switching between multiple tools.

The system is being built to give retail owners and managers an all-in-one dashboard that handles day-to-day tasks—sales recording, barcode generation, customer/vendor tracking, and end-of-day reconciliation—while providing insights through customizable reports. Success for this project means delivering a stable single-page application (SPA) with sub-second response times on key screens, secure user authentication with role-based access control, accurate offline data persistence, and an intuitive interface that non-technical staff can adopt quickly.

## 2. In-Scope vs. Out-of-Scope

**In-Scope (Version 1.0):**
- User registration, login, password reset, and email verification
- Role-based access control (Admin, Manager, Cashier)
- POS interface: product selection, payment capture, receipt printing
- Inventory module: product catalog, stock levels, reorder alerts
- Basic purchase order entry (manual)
- Contact management: customers and vendors with purchase history
- Reporting: sales summary, expense logs, inventory valuation over date ranges
- Barcode generation and printing (JsBarcode + Mustache templates)
- Rich text editing for product descriptions (TinyMCE)
- Client-side image compression and upload
- Offline data caching for key entities (Dexie on IndexedDB)
- System settings panel: currency, tax rates, email config, theme
- Log viewer: filterable, paginated display of application events and errors

**Out-of-Scope (for later phases):**
- Automated supplier purchase order workflows
- Credit card gateway integrations (Stripe, PayPal)
- Advanced analytics (predictive restocking, sales forecasting)
- Multi-store or multi-warehouse support
- Mobile app or native POS terminal software
- Loyalty programs, promotions engine
- Multi-currency and multi-language (beyond base locale)
- Deep third-party integrations (accounting, CRM)

## 3. User Flow
When a new user arrives, they sign up by entering an email and password, then verify their email. After logging in, they land on the Dashboard, which shows quick stats (today’s sales, low-stock items). A left-hand sidebar lets them navigate to Sales (POS screen), Inventory lists, Reports, Contacts, and Settings. Clicking Sales opens a product lookup panel on the left and a transaction summary on the right. The user selects items, applies discounts or taxes, then selects a payment type and clicks “Complete Sale.” A printable receipt dialog pops up.

From the Dashboard or sidebar, a manager can click Inventory to see a searchable table of products with current stock levels, filter by categories, and click any row to edit details or adjust quantities. Under Contacts, they manage customer and vendor profiles, viewing purchase histories or outstanding balances. The Reports page lets them choose a date range and report type, then download or print the results. All data actions are saved to the server, with key records also cached locally to allow continued use when offline.

## 4. Core Features
- **Authentication & Authorization**: Secure login, sign-up, password reset, email verification, role-based permissions.
- **Point-of-Sale (POS)**: Fast item search, cart management, customizable payment options, printable receipts.
- **Inventory Management**: Product catalog CRUD, stock level tracking, manual stock adjustments, low-stock alerts.
- **Contact Management**: Customer/vendor records, search, history logs.
- **Reporting & Analytics**: Sales summaries, expense reports, inventory valuation over custom periods, PDF/CSV export.
- **Barcode Generation**: Dynamic barcode creation with customization (size, format), label templates.
- **Rich Text Editor**: TinyMCE integration for product descriptions and terms.
- **Image Optimization**: Client-side compression before upload to reduce file size.
- **Offline Persistence**: Dexie-powered IndexedDB caching for critical data; sync on reconnect.
- **System Settings**: Global configuration for currency, tax, SMTP, theme colors.
- **Log Viewer**: UI to browse, filter, and paginate application logs (errors, user actions).

## 5. Tech Stack & Tools
**Backend:**
- PHP 8+ with Laravel Framework (MVC, Artisan CLI, Eloquent ORM)
- MySQL (relational database)

**Frontend:**
- React.js (component UI)
- Inertia.js (Laravel ⇄ React bridging for SPA behavior)
- MUI (Material-UI) for pre-built components
- Tailwind CSS for utility-first styling
- Vite (fast build & dev server)

**State & Data:**
- Zustand (lightweight React state management)
- Axios (HTTP requests)
- Dexie.js (IndexedDB wrapper for offline cache)

**Utilities & Libraries:**
- TinyMCE (rich text editor with emoticons & media plugins)
- Day.js (date handling)
- JsBarcode + Mustache (barcode templating)
- browser-image-compression (client-side image shrink)

**IDE/Plugins (recommended):**
- VS Code with ESLint, Prettier, Larave​l Extension Pack, React Extension Pack

## 6. Non-Functional Requirements
- **Performance:** Page transitions under 300 ms, POS screen response under 100 ms for lookups.
- **Scalability:** Support up to 5 concurrent cashiers per store, 10,000 SKUs without noticeable lag.
- **Security:** HTTPS only, CSRF protection, input validation, hashed passwords (bcrypt), role-based access.
- **Reliability:** Offline mode for up to 24 hours of data entry; automatic sync conflict resolution.
- **Usability:** Clean, mobile-responsive layout; MUI accessibility standards (WCAG 2.1 AA).
- **Maintainability:** Strict code style via ESLint/Prettier, modular folder structure, documented API endpoints.

## 7. Constraints & Assumptions
- Requires a PHP 8+ and Node.js environment.
- MySQL or compatible SQL database must be available.
- Inertia.js must remain the SPA bridge—no separate API project.
- Assumes stable network for sync; offline mode only for basic CRUD.
- Users will access via modern browsers (Chrome, Edge, Firefox).
- Barcode printing requires a compatible printer on the client side.

## 8. Known Issues & Potential Pitfalls
- **Offline Sync Conflicts:** Simultaneous edits offline may overwrite data. Mitigation: timestamp-based merge or simple last-write-wins.
- **IndexedDB Limits:** Browser storage quotas vary; keep cache focused on key tables only.
- **Large Inventory Tables:** Rendering thousands of rows can be slow—use virtualized lists or pagination.
- **TinyMCE Bundle Size:** Editor plugins add weight—lazy-load editor only when needed.
- **Barcode Rendering Variations:** Different printers may interpret CSS differently—provide PDF fallback.
- **Authentication Flooding:** Rate-limit login attempts to prevent brute force.

---
*This PRD provides the AI model with an unambiguous, detailed blueprint to guide subsequent technical documentation and implementation steps.*