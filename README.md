# ReliefLink - Disaster Relief Distribution and Priority Management System

A comprehensive web-based integrated system for managing disaster relief distribution and prioritizing barangays based on disaster severity and real-time weather data.

## Project Overview

ReliefLink addresses inefficiencies in manual disaster response by automating the collection, processing, and analysis of disaster-related data. The system connects municipal databases with real-time weather API data to enable data-driven decision-making in relief distribution.

### Key Features

#### User Module (Barangay Officials)
- **User Registration & Authentication**: Secure login and registration for barangay officials
- **Disaster Report Submission**: Submit reports including affected families, damaged houses, and disaster type
- **Report Status Tracking**: View submitted reports and their current status
- **Notifications**: Receive real-time updates on relief distribution and report status

#### Administrative Module (MDRRMO)
- **Dashboard**: Comprehensive overview of active disaster responses
- **Report Review & Processing**: Review disaster reports and update their status
- **Automatic Prioritization**: Intelligent barangay ranking based on severity and weather conditions
- **Relief Inventory Management**: Track and manage available relief supplies
- **Distribution Management**: Record and monitor relief distribution to barangays
- **Weather API Monitoring**: Real-time weather data integration and monitoring
- **Notification Management**: Send targeted notifications to barangay officials

## Technical Architecture

### Technology Stack
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Backend**: PHP 8.x
- **Database**: MySQL (Relational Database)
- **External API**: OpenWeather API for real-time weather data
- **Data Format**: JSON / RESTful API

### System Components

#### 1. Database Schema
The system uses 8 main tables:
- `users` - User accounts (barangay officials and admin)
- `barangays` - Barangay information with coordinates
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
- **WeatherAPI** - OpenWeather API integration
- **PriorityCalculator** - Barangay prioritization algorithm
- **ReliefManagement** - Inventory and distribution management
- **Notification** - Notification system

#### 3. API Endpoints
- `/php/api/auth.php` - Authentication (login, register, logout)
- `/php/api/reports.php` - Disaster reports management
- `/php/api/relief.php` - Inventory and distribution management
- `/php/api/priority.php` - Barangay priority calculation
- `/php/api/weather.php` - Weather data retrieval
- `/php/api/notifications.php` - Notification management

## Installation & Setup

### Prerequisites
- PHP 8.x with MySQLi extension
- MySQL 5.7 or higher
- Web server (Apache, Nginx)
- OpenWeather API key

### Step 1: Database Setup
1. Create a new MySQL database named `relieflink`
2. Import the database schema:
   ```sql
   mysql -u root -p relieflink < db/schema.sql
   ```
3. Update database credentials in `php/config/Database.php`

### Step 2: Configure OpenWeather API
1. Get an API key from [OpenWeatherMap](https://openweathermap.org/api)
2. Update the API key in `php/classes/WeatherAPI.php`:
   ```php
   private $api_key = 'YOUR_OPENWEATHER_API_KEY';
   ```

### Step 3: Web Server Setup
1. Place project files in your web server's document root
2. Ensure PHP can read/write to the project directory
3. Configure web server to serve the `html` folder as the public directory

### Step 4: Initial Setup
1. Navigate to `http://your-domain/login.html`
2. Register a new account (creates a barangay official account)
3. Login with admin credentials to create admin account in database:
   ```sql
   INSERT INTO users (username, email, password_hash, full_name, barangay_name, user_type, is_active)
   VALUES ('admin', 'admin@relieflink.com', '$2y$10$...', 'Admin User', 'MDRRMO', 'admin', 1);
   ```

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

### OpenWeather API
The system integrates with OpenWeather API to:
1. Fetch real-time weather conditions for each barangay
2. Calculate weather impact scores for prioritization
3. Log historical weather data for disaster analysis
4. Provide weather-aware decision support

**Data Retrieved**:
- Temperature (°C)
- Humidity (%)
- Wind Speed (m/s)
- Weather Condition (Clear, Rain, Thunderstorm, etc.)

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
│   └── schema.sql                 # Database schema
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
│       └── notifications.php     # Notification endpoints
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
│       └── manage-notifications.html # Notification management
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
