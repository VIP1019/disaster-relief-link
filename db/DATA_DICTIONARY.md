# ReliefLink — database reference (tables & fields)

Use this file for documentation, ERD captions, and defense Q&A. The live source of truth is `schema.sql`.

---

## Database identity

| Item | Value |
|------|--------|
| **Database name** | `relieflink` |
| **Engine / charset** | InnoDB, `utf8mb4`, collation `utf8mb4_unicode_ci` |
| **Connection in code** | `php/config/Database.php` (`$host`, `$db_name`, `$db_user`, `$db_pass`) |
| **Schema + seed script** | `db/schema.sql` |

---

## Table list (overview)

| # | Table name | Purpose |
|---|------------|---------|
| 1 | `users` | Login accounts: MDRRMO admins and barangay officials. |
| 2 | `barangays` | Master list of barangays with map coordinates (for weather API). |
| 3 | `evacuation_centers` | Shelters / evacuation sites per barangay (capacity, status). |
| 4 | `disaster_reports` | Reports filed by officials: impact, weather snapshot, workflow status. |
| 5 | `relief_inventory` | Stock of relief items (quantity, category, reorder level). |
| 6 | `relief_distributions` | Goods sent out: links report, barangay, inventory line, who released it. |
| 7 | `barangay_priority_ranking` | One row per barangay: computed priority score and rank. |
| 8 | `notifications` | In-app messages to users (optionally tied to a report). |
| 9 | `weather_api_logs` | History of Open-Meteo API responses (JSON payload + parsed fields). |
| 10 | `system_logs` | Audit-style actions (login, report submit, etc.). |

---

## Relationships (foreign keys)

```
barangays (id)
    ├── evacuation_centers.barangay_id  → ON DELETE CASCADE
    ├── disaster_reports.barangay_id
    ├── relief_distributions.barangay_id
    ├── barangay_priority_ranking.barangay_id
    ├── weather_api_logs.barangay_id    → ON DELETE SET NULL
    └── (logical) users.barangay_name should match barangays.name for auto report linking

users (id)
    ├── disaster_reports.user_id       → ON DELETE CASCADE
    ├── relief_inventory.added_by      → ON DELETE SET NULL
    ├── relief_distributions.distributed_by → ON DELETE SET NULL
    ├── notifications.user_id          → ON DELETE CASCADE
    └── system_logs.user_id            → ON DELETE SET NULL

disaster_reports (id)
    ├── relief_distributions.report_id → ON DELETE CASCADE
    ├── notifications.report_id      → ON DELETE CASCADE
    └── (referenced by app logic / status updates)

relief_inventory (id)
    └── relief_distributions.inventory_id
```

---

## 1. `users`

Stores all system accounts.

| Column | Type | Null | Key | Description |
|--------|------|------|-----|-------------|
| `id` | INT | NO | PK, AI | User ID. |
| `username` | VARCHAR(50) | NO | UNIQUE | Login username. |
| `email` | VARCHAR(100) | NO | UNIQUE | Email. |
| `password_hash` | VARCHAR(255) | NO | | Bcrypt hash (`password_hash` in PHP). |
| `full_name` | VARCHAR(100) | NO | | Display name. |
| `barangay_name` | VARCHAR(100) | YES | IDX | Text label; **should match `barangays.name`** so reports resolve `barangay_id`. Admins often use `MDRRMO` or similar. |
| `user_type` | ENUM(`barangay_official`,`admin`) | NO | IDX | Role. |
| `phone_number` | VARCHAR(20) | YES | | Contact. |
| `address` | TEXT | YES | | Address. |
| `is_active` | TINYINT(1) | NO | | `1` = active, `0` = blocked. |
| `created_at` | TIMESTAMP | NO | | Row created. |
| `updated_at` | TIMESTAMP | NO | | Last update. |

---

## 2. `barangays`

Geographic / administrative unit for reports, weather, and priority.

| Column | Type | Null | Key | Description |
|--------|------|------|-----|-------------|
| `id` | INT | NO | PK, AI | Barangay ID. |
| `name` | VARCHAR(100) | NO | UNIQUE, IDX | Official name (matches `users.barangay_name` for linking). |
| `municipality` | VARCHAR(100) | NO | | City / municipality. |
| `province` | VARCHAR(100) | NO | | Province. |
| `population` | INT | YES | | Optional census-style count. |
| `latitude` | DECIMAL(10,8) | NO | | For Open-Meteo `latitude`. |
| `longitude` | DECIMAL(11,8) | NO | | For Open-Meteo `longitude`. |
| `created_at` | TIMESTAMP | NO | | Row created. |

---

## 3. `evacuation_centers`

Evacuation or shelter capacity per barangay.

| Column | Type | Null | Key | Description |
|--------|------|------|-----|-------------|
| `id` | INT | NO | PK, AI | Center ID. |
| `barangay_id` | INT | NO | FK, IDX | → `barangays.id`. |
| `center_name` | VARCHAR(150) | NO | | Site name. |
| `address` | TEXT | YES | | Location text. |
| `capacity` | INT | NO | | Max persons. |
| `current_occupancy` | INT | NO | | Current headcount. |
| `contact_person` | VARCHAR(100) | YES | | PIC name. |
| `contact_phone` | VARCHAR(20) | YES | | PIC phone. |
| `facilities` | TEXT | YES | | Notes (kitchen, medical, etc.). |
| `status` | ENUM(`open`,`full`,`closed`) | NO | IDX | Operational status. |
| `latitude` | DECIMAL(10,8) | YES | | Optional map point. |
| `longitude` | DECIMAL(11,8) | YES | | Optional map point. |
| `created_at` | TIMESTAMP | NO | | Row created. |
| `updated_at` | TIMESTAMP | NO | | Last update. |

---

## 4. `disaster_reports`

Core incident records from barangay officials.

| Column | Type | Null | Key | Description |
|--------|------|------|-----|-------------|
| `id` | INT | NO | PK, AI | Report ID. |
| `user_id` | INT | NO | FK | Submitter → `users.id`. |
| `barangay_id` | INT | NO | FK, IDX | Affected barangay → `barangays.id`. |
| `disaster_type` | VARCHAR(100) | NO | | e.g. Flood, Storm. |
| `affected_families` | INT | NO | | Impact metric. |
| `damaged_houses` | INT | NO | | Impact metric. |
| `injured_count` | INT | NO | | Default 0. |
| `death_count` | INT | NO | | Default 0. |
| `description` | TEXT | YES | | Narrative. |
| `weather_condition` | VARCHAR(200) | YES | | From API / snapshot. |
| `temperature` | DECIMAL(5,2) | YES | | °C at submission. |
| `humidity` | INT | YES | | % at submission. |
| `wind_speed` | DECIMAL(6,2) | YES | | m/s at submission. |
| `severity_level` | ENUM(`low`,`medium`,`high`,`critical`) | NO | IDX | Derived / stored severity. |
| `status` | ENUM(`submitted`,`reviewed`,`prioritized`,`relief_distributed`) | NO | IDX | Workflow state. |
| `submitted_at` | TIMESTAMP | NO | | Created time. |
| `updated_at` | TIMESTAMP | NO | | Last change. |

---

## 5. `relief_inventory`

Warehouse-style stock lines.

| Column | Type | Null | Key | Description |
|--------|------|------|-----|-------------|
| `id` | INT | NO | PK, AI | Item row ID. |
| `item_name` | VARCHAR(100) | NO | | Item label. |
| `category` | VARCHAR(50) | NO | IDX | Food, Water, NFIs, etc. |
| `quantity` | INT | NO | | On-hand count. |
| `unit_of_measure` | VARCHAR(20) | YES | | sack, bottle, kit… |
| `description` | TEXT | YES | | Notes. |
| `reorder_level` | INT | YES | | Low-stock alert threshold. |
| `cost_per_unit` | DECIMAL(10,2) | YES | | Optional costing. |
| `added_by` | INT | YES | FK | Admin user → `users.id`. |
| `created_at` | TIMESTAMP | NO | | Row created. |
| `updated_at` | TIMESTAMP | NO | | Last update. |

---

## 6. `relief_distributions`

Each row is one outbound movement of stock tied to a report and barangay.

| Column | Type | Null | Key | Description |
|--------|------|------|-----|-------------|
| `id` | INT | NO | PK, AI | Distribution ID. |
| `report_id` | INT | NO | FK, IDX | → `disaster_reports.id`. |
| `barangay_id` | INT | NO | FK, IDX | Recipient barangay. |
| `inventory_id` | INT | NO | FK | → `relief_inventory.id`. |
| `quantity_distributed` | INT | NO | | Units taken from stock. |
| `distribution_date` | TIMESTAMP | NO | | When it happened. |
| `distributed_by` | INT | YES | FK | Staff → `users.id`. |
| `notes` | TEXT | YES | | Delivery notes. |

---

## 7. `barangay_priority_ranking`

Cached ranking from `PriorityCalculator` (one row per barangay).

| Column | Type | Null | Key | Description |
|--------|------|------|-----|-------------|
| `id` | INT | NO | PK, AI | Row ID. |
| `barangay_id` | INT | NO | FK, UNIQUE | → `barangays.id`. |
| `priority_score` | DECIMAL(10,2) | YES | IDX | Combined score (higher = more urgent). |
| `affected_families_total` | INT | YES | | Aggregated from reports. |
| `damaged_houses_total` | INT | YES | | Aggregated. |
| `weather_impact_score` | DECIMAL(10,2) | YES | | Weather component. |
| `overall_severity` | VARCHAR(20) | YES | | e.g. Low / Medium / High / Critical. |
| `ranking_position` | INT | YES | | 1 = top priority. |
| `calculated_at` | TIMESTAMP | NO | | When last calculated. |

---

## 8. `notifications`

User inbox messages.

| Column | Type | Null | Key | Description |
|--------|------|------|-----|-------------|
| `id` | INT | NO | PK, AI | Notification ID. |
| `user_id` | INT | NO | FK, IDX | Recipient → `users.id`. |
| `report_id` | INT | YES | FK | Optional link → `disaster_reports.id`. |
| `notification_type` | VARCHAR(100) | NO | | e.g. `report_status`, `relief`. |
| `message` | TEXT | NO | | Body text. |
| `is_read` | TINYINT(1) | NO | IDX | `0` unread, `1` read. |
| `created_at` | TIMESTAMP | NO | | Created time. |

---

## 9. `weather_api_logs`

Each logged Open-Meteo response for traceability.

| Column | Type | Null | Key | Description |
|--------|------|------|-----|-------------|
| `id` | INT | NO | PK, AI | Log ID. |
| `barangay_id` | INT | YES | FK, IDX | → `barangays.id` (nullable if unknown). |
| `api_response` | LONGTEXT | YES | | Raw JSON string from API. |
| `temperature` | DECIMAL(5,2) | YES | | Parsed °C. |
| `humidity` | INT | YES | | Parsed %. |
| `wind_speed` | DECIMAL(6,2) | YES | | Parsed m/s. |
| `weather_condition` | VARCHAR(200) | YES | | e.g. Rain, Clouds. |
| `api_call_time` | TIMESTAMP | NO | IDX | When logged. |

---

## 10. `system_logs`

Light audit trail for important actions.

| Column | Type | Null | Key | Description |
|--------|------|------|-----|-------------|
| `id` | INT | NO | PK, AI | Log ID. |
| `action` | VARCHAR(255) | NO | | Short action code / label. |
| `user_id` | INT | YES | FK | Actor → `users.id`. |
| `description` | TEXT | YES | | Details. |
| `ip_address` | VARCHAR(45) | YES | | IPv4/IPv6. |
| `created_at` | TIMESTAMP | NO | IDX | Event time. |

---

## ENUM quick reference

| Table | Column | Allowed values |
|-------|--------|------------------|
| `users` | `user_type` | `barangay_official`, `admin` |
| `evacuation_centers` | `status` | `open`, `full`, `closed` |
| `disaster_reports` | `severity_level` | `low`, `medium`, `high`, `critical` |
| `disaster_reports` | `status` | `submitted`, `reviewed`, `prioritized`, `relief_distributed` |

---

## Copy-paste summary (documentation / manuscript)

**Database name:** `relieflink`  
**Tables:** `users`, `barangays`, `evacuation_centers`, `disaster_reports`, `relief_inventory`, `relief_distributions`, `barangay_priority_ranking`, `notifications`, `weather_api_logs`, `system_logs`  
**Main flows:** Officials (`users` + `barangays`) submit `disaster_reports`; weather is stored in `weather_api_logs`; MDRRMO manages `relief_inventory` and records `relief_distributions`; priorities are stored in `barangay_priority_ranking`; users see `notifications`; `system_logs` records key actions.
