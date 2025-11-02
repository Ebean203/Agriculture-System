# DFD Level 1 Guide — Lagonglong FARMS (by Module)

This guide walks you through crafting a Level 1 Data Flow Diagram (DFD) for the Lagonglong FARMS system. It is organized by system modules (transactions) and tells you exactly where to place processes, data stores, and external entities; which arrows to draw; and how to label each data flow.

No sensitive configuration (like DB passwords) is included. Names below match files and features in this repository for traceability.

---

## DFD Symbols and Conventions

- External Entity (square): outside actors interacting with the system (e.g., MAO Staff, Farmer, LGU Management)
- Process (rounded rectangle or circle): numbered per module (1.0, 2.0, …)
- Data Store (open-ended rectangle): labeled D1, D2, … (represents persistent storage)
- Data Flow (arrow): label with the name of data being transferred

Numbering and naming:
- Processes are numbered by module: 1.0 Authentication, 2.0 Farmers (includes program flags & boat info as derived attributes), 3.0 Inventory, 4.0 Distribution & Visitation, 5.0 Activities, 6.0 Reports, 7.0 Notifications, 8.0 Analytics, 9.0 Staff & Roles.
- Sub-flows inside a module can be 1.1, 1.2 (optional in L1; you can reserve that for L2).

Layout guidance (plain language):
- Put external entities at the far left and far right sides.
- Put processes in two middle columns:
   - Left column: Authentication (1.0) at top, Farmers (2.0) and Activities (5.0) beneath.
   - Right column: Inventory (3.0) and Distribution & Visitation (4.0) in the middle; Notifications (7.0) and Reports (6.0) lower.
   - Analytics (8.0) can sit near the top center as it reads from multiple stores.
- Place data stores along the bottom, roughly under the process that owns/updates them.
- Draw vertical arrows down to data stores where possible to avoid crossing lines.

---

## External Entities

- E1: MAO Staff (primary user of the app)
- E2: Farmer (source of registration data; may provide documents/photos via staff)
- E3: LGU Management (consumer of exported reports; could be the same as E1 but shown separately in DFD)

Placement: Put E1 (MAO Staff) on the left near the top, E2 (Farmer) on the left mid, and E3 (LGU Management) on the right side (optional; only if you want to show report consumers).

---

## Data Stores

- D1: Staff & Auth DB (tables: `mao_staff`, `roles`) — under 1.0 (place at C2-R5)
- D2: Farmers DB (tables: `farmers`, `barangays`, optional program flags) — primary store for person records. Note: RSBSA/NCFRS/FishR membership and Boat info are treated as attributes/flags derived from farmer records within this system. There is no external integration to those programs in scope.
- D3: Commodities & Categories (tables: `commodities`, `input_categories`) — under 2.0/3.0 (place at C2.5-R5)
- D4: Inventory DB (table: `mao_inventory`) — under 3.0 (place at C3-R5)
- D5: Distribution Log DB (table: `mao_distribution_log` and reschedules) — under 4.0 (place at C3-R5)
- D6: Activities DB (tables: `mao_activities`, related modals) — under 5.0 (place at C3-R5)
- D7: Reports Index DB (table: `generated_reports`) — under 6.0 (place at C2-R5)
- D8: Activity Logs (table: `activity_logs`) — shared (place centrally at C2.5-R5)
- F1: Farmer Photos Storage (folder: `uploads/farmer_photos/`) — under 2.0 (place at C2-R5, right of D2)
- F2: Report Files Storage (folder: `reports/`) — under 6.0 (place at C2-R5, right of D7)

Note: F1 and F2 are file stores represented with the same data store symbol.

---

## Process 1.0 — Authentication & Sessions (login.php, check_session.php, logout.php)

Position: Top-left of the process area

Arrows:
- E1 → 1.0 label: "Credentials (username, password)"
- 1.0 → D1 label: "Lookup staff by username"
- D1 → 1.0 label: "Staff record (hash, role)"
- 1.0 → E1 label: "Session established / Auth result"
- 1.0 → D8 label: "Activity entry: login/logout"

Connection notes:
- Ensure the D1 arrows attach to the open side of the data store symbol.
- The return arrow from 1.0 to E1 should be on the right side of E1 to avoid crossing with other flows.

---

## Process 2.0 — Farmers Registry (farmers.php, farmer_regmodal.php, farmer_editmodal.php, upload_farmer_photo.php)

Position: Left-middle

Arrows:
- E1 → 2.0 label: "Farmer profile input / search criteria"
- E2 → 2.0 label: "Farmer-provided data (via staff)"
- 2.0 → D2 label: "Create/Update farmer, read listing"
- D2 → 2.0 label: "Farmer records"
- 2.0 → F1 label: "Upload photo file"
- 2.0 → D8 label: "Activity entry: add/edit farmer, upload photo"

Optional (search helpers):
- 2.0 ↔ D2 label both ways: "Search by name/contact" (covers endpoints like `search_farmers.php`, `get_farmers.php`)

Placement tip: Place D2 and F1 directly below 2.0 in R5 to keep flows straight.

---

## Process 3.0 — Inventory Management (mao_inventory.php, add_new_input.php, update_inventory.php)

Position: Right-middle (upper)

Arrows:
- E1 → 3.0 label: "New input details / stock adjustments"
- 3.0 ↔ D3 label both ways: "Input categories & commodity refs"
- 3.0 ↔ D4 label both ways: "Inventory read/write (add, adjust, stock-out)"
- 3.0 → D8 label: "Activity entry: inventory changes"

Placement tip: Place D3 slightly left of D4 in R5 so the category references and inventory updates don’t overlap.

---

## Process 4.0 — Distribution & Visitation (distribute_input.php, input_distribution_records.php, mark_distribution_complete.php, get_distribution_reschedules.php)

Position: Right-middle (lower)

Arrows:
- E1 → 4.0 label: "Distribution form data / visitation date"
- 4.0 ↔ D2 label both ways: "Farmer selection & contact"
- 4.0 ↔ D4 label both ways: "Inventory decrement / validation"
- 4.0 ↔ D5 label both ways: "Distribution log create/read; reschedules"
- 4.0 → D8 label: "Activity entry: distribute, reschedule, mark complete"

Filters & exports:
- E1 → 4.0 label: "Search/filters/pagination"
- 4.0 → E1 label: "Exported distribution HTML (via action=export_pdf)"

Placement tip: To avoid crossing with 3.0 flows, route the D4 ↔ 4.0 arrows vertically.

---

## Process 5.0 — MAO Activities (mao_activities.php, includes/mao_activities_modals.php, all_activities.php)

Position: Left-middle (lower)

Arrows:
- E1 → 5.0 label: "Create/update activity"
- 5.0 ↔ D6 label both ways: "Activities read/write"
- 5.0 → D8 label: "Activity entry: activity create/update"
- E1 → 5.0 label: "Filter by activity_id (from notifications/deep link)"

Placement tip: Keep D6 just below 5.0; leave left-hand space for incoming filters from E1.

---

## Process 6.0 — Reporting (reports.php, get_saved_reports.php, get_saved_reports_count.php, pdf_export.php, export_distribution_pdf.php)

Position: Lower-left

Arrows:
- E1 → 6.0 label: "Report request (type, date range, filters)"
- 6.0 ↔ D2/D4/D5/D6 label: "Fetch data for report" (draw separate arrows to each relevant store)
- 6.0 → F2 label: "Saved report HTML file"
- 6.0 ↔ D7 label both ways: "Index saved report (insert/list)"
- 6.0 → D8 label: "Activity entry: generated/saved report"
- 6.0 → E3 label: "Printable report output"

Placement tip: Place F2 to the right of D7 so file output arrow doesn’t cross the index arrows.

---

## Process 7.0 — Notifications (includes/notification_system.php, get_notifications.php, includes/notification_complete.php)

Position: Lower-right

Arrows:
- 7.0 ← D5 label: "Upcoming visitations (≤7 days), overdue"
- 7.0 ← D4 label: "Low/critical stock"
- 7.0 ← D6 label: "Activities (today, tomorrow, next 14 days, overdue)"
- 7.0 ← D4 label: "Expiring input batches (≤10 days; expired)"
- 7.0 → E1 label: "Notification list + counts"
- E1 → 7.0 label: "Open notification (click)"

Deep-link behavior:
- 7.0 → 5.0 label: "Open activities filtered by activity_id"
- 7.0 → 4.0 label: "Open distributions filtered (farmer/log_id if implemented)"
- 7.0 → 3.0 label: "Open expiring inputs filtered by inventory_id/input_id"

Placement tip: Draw the incoming arrows from D4/D5/D6 downward into 7.0 to avoid crossovers.

---

## Process 8.0 — Analytics & Dashboard (index.php, analytics_dashboard.php, get_report_data.php, get_top_producers.php)

Position: Near the top center

Arrows:
- E1 → 8.0 label: "Open dashboard / analytics view"
- 8.0 ← D2/D4/D5/D6/D3 label: "Aggregated metrics & chart data" (separate arrows per store)
- 8.0 → E1 label: "Charts & KPIs (JSON→UI)"

Placement tip: If you keep 8.0 near the top, route its data store arrows along the diagram edges to minimize crossings.

---

## Process 9.0 — Staff & Roles (staff.php, staff_actions.php)

Position: Between Farmers and Inventory on the left side

Arrows:
- E1 → 9.0 label: "Add staff / manage roles"
- 9.0 ↔ D1 label both ways: "Create staff, list staff, role assignment"
- 9.0 → D8 label: "Activity entry: staff changes"

Placement tip: Place 9.0 closer to D1 to keep the lines short.

---

<!-- Maritime removed as a standalone process per scope clarification. See Farmers (2.0) notes below. -->

---

## Arrow Label Cheat-Sheet (use exact labels)

- Credentials (username, password)
- Session established / Auth result
- Lookup staff by username / Staff record (hash, role)
- Farmer profile input / Farmer records / Search criteria
- Upload photo file
- Input categories & commodity refs
- Inventory read/write / Inventory decrement / validation
- Distribution log create/read
- Reschedule request / Reschedule history
- Create/update activity / Activity records
- Report request (type, date range) / Printable report output
- Saved report HTML file / Index saved report
- Upcoming visitations (≤7 days)
- Low/critical stock
- Activities (today→14 days, overdue)
- Expiring input batches (≤10 days)
- Notification list + counts / Open notification (click)
- Charts & KPIs (JSON→UI)
- Activity entry: <action>

Tip: Keep labels short but precise; prefer the terms above for consistency.

---

## Step-by-Step Build Instructions

1) Place External Entities
   - E1 (MAO Staff) on the far left near the top
   - E2 (Farmer) on the far left mid area
   - E3 (LGU Management) on the far right mid area (optional)

2) Add Processes (boxes) by module
   - Top: 1.0 Authentication and 8.0 Analytics (centered)
   - Left column: 2.0 Farmers (above) and 5.0 Activities (below)
   - Right column: 3.0 Inventory (above) and 4.0 Distribution (below)
   - Bottom row (process level): 6.0 Reports (left) and 7.0 Notifications (right); 9.0 Staff & Roles between Farmers and Inventory on the left

3) Add Data Stores (bottom row)
   - Place D1 under Authentication; D2 and F1 under Farmers; D3 and D4 under Inventory; D5 under Distribution; D6 under Activities; D7 and F2 under Reports; put D8 (Activity Logs) roughly centered at the bottom.

4) Draw Data Flows and Label Them
   - Follow each module section above; connect arrow tails near the origin and arrow heads to the target; align arrows vertically where possible to avoid crossings.
   - For data stores, connect on the open side of the store symbol; use straight vertical connectors where possible.

5) Add Activity Log Flows
   - From 1.0/2.0/3.0/4.0/5.0/6.0/9.0 add a thin arrow down to D8 labeled "Activity entry: <action>".

6) Add Notification Deep Links
   - From 7.0 to 5.0, 4.0, and 3.0 as described; these show how a click routes the user to filtered views.

7) Final Pass
   - Ensure each data store engaged by a process has both directions if reads and writes occur.
   - Ensure external entities only connect to processes (not directly to data stores).
   - Keep labels legible and non-overlapping; use elbow connectors if necessary.

---

## Example Minimal Context Diagram (for reference)

- One process: 0.0 Lagonglong FARMS (System boundary)
- E1 → 0.0: Credentials, Requests
- 0.0 → E1/E3: Pages, Reports, Notifications
- 0.0 ↔ D1..D8: All persistent storage

Use this only to frame the Level 1; the bulk of your diagram should follow the module breakdown above.

---

## Cross-Checking with the Codebase

- Authentication: `login.php`, `check_session.php`, `logout.php`
- Farmers: `farmers.php`, `farmer_regmodal.php`, `farmer_editmodal.php`, `upload_farmer_photo.php`
- Inventory: `mao_inventory.php`, `add_new_input.php`, `update_inventory.php`, `stockout_batch.php`
- Distribution: `distribute_input.php`, `input_distribution_records.php`, `mark_distribution_complete.php`, `get_distribution_reschedules.php`
- Activities: `mao_activities.php`, `includes/mao_activities_modals.php`, `all_activities.php`
- Reporting: `reports.php`, `get_saved_reports.php`, `get_saved_reports_count.php`, `pdf_export.php`, `export_distribution_pdf.php`
- Notifications: `includes/notification_system.php`, `get_notifications.php`, `includes/notification_complete.php`
- Analytics: `index.php`, `analytics_dashboard.php`, `get_report_data.php`, `get_top_producers.php`
- Staff: `staff.php`, `staff_actions.php`
- Maritime views (derived, no external registration): `boat_records.php`, `ncfrs_records.php`, `fishr_records.php` — these are just filters/listings based on flags in `farmers` and are part of Farmers (2.0), not a separate process.

---

## Clear Process Definitions (inputs, outputs, stores)

Use this as a checklist when drawing each process:

- 1.0 Authentication & Sessions
   - Inputs: Credentials from MAO Staff
   - Outputs: Auth result/session; activity log
   - Stores: D1 Staff & Auth (read), D8 Activity Logs (write)

- 2.0 Farmers Registry
   - Inputs: Farmer details, search criteria, photo files
   - Outputs: Farmer records, upload confirmation; activity log; derived program flags & boat info for views
   - Stores: D2 Farmers (read/write; includes flags for RSBSA/NCFRS/FishR/Boat as checkmarks to be verified), F1 Farmer Photos (write), D8 (write)
   - Note: RSBSA/NCFRS/FishR membership and Boat ownership are captured as checkmarks/attributes in D2 based on what farmers report. There is no external registration or system integration. Views like `boat_records.php`, `ncfrs_records.php`, and `fishr_records.php` simply filter and display these flags.

- 3.0 Inventory Management
   - Inputs: New input details, adjustments
   - Outputs: Updated inventory; activity log
   - Stores: D3 Commodities/Categories (read), D4 Inventory (read/write), D8 (write)

- 4.0 Distribution & Visitation
   - Inputs: Distribution form, visitation date, filters
   - Outputs: Distribution log updates, decremented inventory, exports; activity log
   - Stores: D2 Farmers (read), D4 Inventory (read/write), D5 Distribution Log (read/write), D8 (write)

- 5.0 MAO Activities
   - Inputs: Activity create/update, filters
   - Outputs: Activity records; activity log
   - Stores: D6 Activities (read/write), D8 (write)

- 6.0 Reporting
   - Inputs: Report request (type, dates)
   - Outputs: Saved report files, indexed reports; activity log; printable output
   - Stores: D2/D4/D5/D6 (read), F2 Reports (write), D7 Reports Index (read/write), D8 (write)

- 7.0 Notifications
   - Inputs: None from user; system queries data stores
   - Outputs: Notification list/counts; deep links
   - Stores: D4/D5/D6 (read)

- 8.0 Analytics & Dashboard
   - Inputs: Page load request
   - Outputs: Charts & KPIs
   - Stores: D2/D3/D4/D5/D6 (read)

- 9.0 Staff & Roles
   - Inputs: Staff add/manage requests
   - Outputs: Updated staff records; activity log
   - Stores: D1 Staff & Auth (read/write), D8 (write)

<!-- Maritime process removed; see Farmers (2.0) for derived views note. -->

This mapping ensures your DFD Level 1 reflects actual modules and data movements in the system.
