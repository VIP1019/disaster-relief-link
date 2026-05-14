# ReliefLink - Disaster Relief Distribution and Priority Management System

A comprehensive web-based integrated system for managing disaster relief distribution and prioritizing barangays based on disaster severity and real-time weather data.

## Project Overview

ReliefLink addresses inefficiencies in manual disaster response by automating the collection, processing, and analysis of disaster-related data for the **Municipality of Daet, Camarines Norte**. The system connects municipal databases with real-time weather API data to enable data-driven decision-making in relief distribution.

### Key Features

#### User Module (Barangay Officials)
- **User Registration & Authentication**: Secure login and registration for barangay officials
- **Disaster Report Submission**: Submit reports including affected families, damaged houses, and disaster type
- **Report Status Tracking**: View submitted reports; **edit or delete** reports while still in **submitted** status
- **Notifications**: **List, mark read, mark all read, and delete** in-app notifications

#### Administrative Module (MDRRMO)
- **Dashboard**: Comprehensive overview of active disaster responses
- **Report Review & Processing**: Review disaster reports and update their status
- **Automatic Prioritization**: Intelligent barangay ranking based on severity and weather conditions
- **Relief Inventory Management**: Track, **add, edit, and delete** relief supplies (delete only when no distribution history)
- **Distribution Management**: Record and monitor relief distribution to barangays
- **Evacuation centers**: Maintain **shelter / evacuation site** records per barangay (CRUD)
- **Weather API Monitoring**: Real-time weather data integration and monitoring
- **Notification Management**: **Send, list, and remove** targeted notifications to barangay officials

## Technical Architecture

### Technology Stack
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Backend**: PHP 8.x
- **Database**: MySQL (Relational Database)
- **External API**: [Open-Meteo](https://open-meteo.com/) Forecast API for current weather (no API key for typical non-commercial use)
- **Data Format**: JSON / RESTful API

### System Components

#### 1. Database Schema
The system uses 10 main tables:
- `users` - User accounts (barangay officials and admin)
- `barangays` - Barangay information with coordinates
- `evacuation_centers` - Shelters / evacuation sites per barangay (capacity, occupancy, contacts)
- `disaster_reports` - Disaster event reports with impact data
- `relief_inventory` - Available relief supplies and quantities
- `relief_distributions` - Distribution records of relief goods
- `barangay_priority_ranking` - Calculated priority scores for each barangay
- `notifications` - User notifications and alerts
- `weather_api_logs` - Historical weather data from API calls
- `system_logs` - System activity logs for auditing

#### 2. PHP Classes
- **Database** - Database connection management
- **Auth** - User authentication and session management
- **DisasterReport** - Disaster report CRUD operations
- **WeatherAPI** - [Open-Meteo](https://open-meteo.com/) forecast / current conditions and `weather_api_logs`
- **PriorityCalculator** - Barangay prioritization algorithm
- **ReliefManagement** - Inventory and distribution management
- **EvacuationCenter** - Evacuation / shelter site records
- **Notification** - Notification system

#### 3. API Endpoints
- `/php/api/auth.php` - Authentication (login, register, logout, session check)
- `/php/api/reports.php` - Disaster reports (submit, list, get, **update_own**, **delete_own**, admin list/status)
- `/php/api/relief.php` - Inventory (**add/update/delete**), **barangays_list**, distributions, statistics
- `/php/api/priority.php` - Barangay priority calculation
- `/php/api/weather.php` - Weather data retrieval
- `/php/api/notifications.php` - Notifications (list, create, mark read, delete, **list_recipients** for admins)
- `/php/api/evacuation.php` - **Evacuation centers / shelters** (list, get, create, update, delete — admin for writes)

## Installation & Setup

### Prerequisites
- PHP 8.x with MySQLi extension
- MySQL 5.7 or higher
- Web server (Apache, Nginx)
- PHP cURL extension **recommended** (HTTPS to `api.open-meteo.com`; `file_get_contents` is used as fallback)

### Step 1: Database Setup
1. Import the database schema (creates DB `relieflink` if missing, then tables + seed):
   - **phpMyAdmin:** open the **SQL** tab (from the home/server screen is fine), paste the full contents of `db/schema.sql`, click **Go**.
   - **Command line:** `mysql -u root -p < db/schema.sql` (from the project folder, adjust user if needed)
2. Update database credentials in `php/config/Database.php`
3. For a **table-by-table list** (database name, column names, types, keys), see `db/DATA_DICTIONARY.md`.

### Step 2: Weather (Open-Meteo)
1. **No API key is required** for normal use: ReliefLink calls the public [Open-Meteo Forecast API](https://open-meteo.com/) using each barangay’s latitude/longitude from the database.
2. Your server must allow **outbound HTTPS** to `api.open-meteo.com` (firewall / hosting policy).
3. In **Weather Monitoring** (admin), click **Force API Sync** to refresh all barangays and write rows to `weather_api_logs`.
4. **Optional:** self-hosted or alternate Open-Meteo endpoint — set environment variable `OPEN_METEO_FORECAST_URL` to the base forecast URL (e.g. `https://api.open-meteo.com/v1/forecast`), or add `open_meteo_forecast_url` in `php/config/weather.local.php` (see `weather.local.example.php`). Respect [Open-Meteo terms](https://open-meteo.com/) and cite the data source in academic work.

### Step 3: Web Server Setup
1. Place project files in your web server's document root
2. Ensure PHP can read/write to the project directory
3. Configure web server to serve the `html` folder as the public directory

### Step 4: Demonstration test accounts (included in `db/schema.sql`)

After importing `db/schema.sql`, you can log in immediately with:

| Role | Username | Password | Barangay (match `barangays.name` when registering) |
|------|----------|----------|--------------------------------------------------------|
| MDRRMO (admin) | `admin` | `Demo@2026` | — |
| Barangay official | `brgyuser` | `Demo@2026` | `Poblacion` |
| Barangay official | `captain_cb` | `Demo@2026` | `Camambugan` |
| Barangay official | `captain_bg` | `Demo@2026` | `Bagasbas` |

The seed data includes barangays **Poblacion**, **Camambugan**, and **Bagasbas** (Daet), sample disaster reports, relief inventory, one distribution record, and notifications. **Barangay officials should register using a `barangay_name` that exactly matches a row in `barangays.name`** (e.g. `Poblacion`) so report submission can resolve `barangay_id` automatically.

If you prefer to create the admin manually instead of using the seed file, you can still use `password_hash()` in PHP to generate a new hash and `INSERT` into `users`.

## Prioritization Algorithm

The system uses a weighted algorithm to calculate barangay priority scores:

```
Priority Score = (Affected Families × 0.4) + (Damaged Houses × 0.3) + 
                 (Weather Impact × 0.2) + (Severity Level × 0.1)
```

### Weather Impact Calculation
- **Temperature**: Extreme temperatures (< 10°C or > 40°C) increase impact
- **Humidity**: High humidity (> 85%) increases disease risk
- **Wind Speed**: Strong winds (> 25 m/s) increase property damage risk

### Severity Levels
- **Critical**: Priority Score ≥ 75
- **High**: Priority Score 50-74
- **Medium**: Priority Score 25-49
- **Low**: Priority Score < 25

## API Integration Details

### Open-Meteo API
The system integrates with the [Open-Meteo](https://open-meteo.com/) Forecast API to:
1. Fetch **current** temperature, humidity, wind (m/s), and WMO weather code at each barangay coordinate
2. Support weather impact inputs for prioritization (via stored conditions and logs)
3. Log JSON responses in `weather_api_logs` for auditing and the admin weather table

**Data retrieved (current)**:
- Temperature (°C, `temperature_2m`)
- Humidity (% , `relative_humidity_2m`)
- Wind speed (m/s, `wind_speed_10m`)
- Condition label derived from WMO `weather_code` (e.g. Clear, Rain, Thunderstorm)

## User Workflows

### Barangay Official Workflow
1. **Register** → Create account with barangay details
2. **Login** → Access personal dashboard
3. **Submit Report** → Fill disaster impact form with weather auto-capture
4. **Monitor Status** → Track report progress through system
5. **Receive Notifications** → Get updates on relief distribution

### Admin Workflow
1. **Login** → Access admin dashboard
2. **Review Reports** → Process incoming barangay reports
3. **Calculate Priorities** → Run prioritization algorithm
4. **Manage Inventory** → Add/update relief supplies
5. **Record Distribution** → Log relief delivery to barangays
6. **Monitor Weather** → Track API data and weather conditions
7. **Send Notifications** → Communicate with barangay officials

## File Structure

```
/vercel/share/v0-project/
├── db/
│   ├── schema.sql                 # Database schema + seed data
│   └── DATA_DICTIONARY.md         # Table & column reference (for docs / defense)
├── php/
│   ├── config/
│   │   └── Database.php          # Database connection
│   ├── classes/
│   │   ├── Auth.php              # Authentication
│   │   ├── DisasterReport.php    # Report management
│   │   ├── WeatherAPI.php        # Weather API integration
│   │   ├── PriorityCalculator.php # Prioritization algorithm
│   │   ├── ReliefManagement.php  # Inventory & distribution
│   │   └── Notification.php      # Notification system
│   └── api/
│       ├── auth.php              # Auth endpoints
│       ├── reports.php           # Report endpoints
│       ├── relief.php            # Relief endpoints
│       ├── priority.php          # Priority endpoints
│       ├── weather.php           # Weather endpoints
│       ├── notifications.php     # Notification endpoints
│       └── evacuation.php        # Evacuation / shelter CRUD
├── html/
│   ├── login.html                # Login page
│   ├── register.html             # Registration page
│   ├── user/
│   │   ├── dashboard.html        # User dashboard
│   │   ├── submit-report.html    # Report submission
│   │   ├── view-reports.html     # Report list
│   │   └── notifications.html    # Notifications
│   └── admin/
│       ├── dashboard.html        # Admin dashboard
│       ├── review-reports.html   # Report review
│       ├── prioritize-barangays.html # Prioritization
│       ├── relief-inventory.html # Inventory management
│       ├── distribution.html     # Distribution recording
│       ├── weather-monitoring.html # Weather monitoring
│       ├── manage-notifications.html # Notification management
│       └── evacuation-centers.html # Evacuation / shelter registry
├── css/
│   └── styles.css                # Global styles
└── README.md                     # This file
```

## Security Features

- **Password Hashing**: Bcrypt hashing for secure password storage
- **Session Management**: PHP session-based authentication
- **Input Validation**: Server-side validation for all inputs
- **SQL Injection Prevention**: Prepared statements for all queries
- **CSRF Protection**: Form validation for sensitive operations
- **Role-Based Access Control**: Different permissions for users and admins

## Future Enhancements

- SMS/Email notifications
- Mobile application
- Map visualization of affected areas
- Advanced analytics and reporting
- Multi-language support
- Offline functionality
- Social media integration
- Resource allocation optimization
- Disaster prediction modeling

## Support & Contact

For issues, questions, or contributions, please contact the development team.

## License

This project is developed for academic and municipal disaster relief purposes.

---

**Developed by**: ReliefLink Development Team
**Last Updated**: April 2026
