# CN Track

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4.svg)](https://php.net)
[![SQLite](https://img.shields.io/badge/SQLite-WAL-orange.svg)](https://sqlite.org)
[![GitHub last commit](https://img.shields.io/github/last-commit/incredibleamir-dot/cn-rail-container-tracking)](https://github.com/incredibleamir-dot/cn-rail-container-tracking)
[![GitHub issues](https://img.shields.io/github/issues/incredibleamir-dot/cn-rail-container-tracking)](https://github.com/incredibleamir-dot/cn-rail-container-tracking/issues)
[![CI](https://github.com/incredibleamir-dot/cn-rail-container-tracking/actions/workflows/php.yml/badge.svg)](https://github.com/incredibleamir-dot/cn-rail-container-tracking/actions/workflows/php.yml)

A self-hosted container tracking application for CN Rail shipments. Track containers, manage shipments, plan deliveries, and analyze shipping data — all from a clean web dashboard.

## Features

- **Container Tracking** — Real-time tracking via CN Rail API or Google Sheets data source
- **Shipment Management** — Group containers into shipments with BOL, PO, customer info
- **Delivery Planner** — Schedule deliveries with detention day calculations (free days, working days, day of interchange)
- **Analysis Dashboard** — Track statistics, tagging, filtering
- **Auto-Refresh** — Cron job tracks containers every 5 minutes
- **Multi-User** — PIN-based auth with admin/user roles
- **Archive** — Auto-archives containers on OUT-GATE events

## Requirements

- PHP 8.0+ with extensions: `pdo_sqlite`, `curl`, `json`
- SQLite3 (via PHP PDO)
- CN Rail API credentials (or Google Sheets URL)

## Installation

1. Clone or download this repo into your web server's document root
2. Copy `config.example.php` to `config.php` and fill in your CN Rail API keys
3. Create the `data/` directory and make it writable by the web server:
   ```
   mkdir data
   chmod 775 data
   ```
4. Navigate to `http://localhost/install.php` to initialize the database
5. Delete `install.php` after setup
6. Set `DEBUG_MODE` to `false` in `config.php` for production

### Apache

The included `.htaccess` handles URL rewriting automatically.

### PHP Built-in Server

```bash
php -S localhost:8080 php-router.php
```

## Auto-Tracking (Cron Job)

Run `cron-track.php` via cron to refresh all containers every 5 minutes:

```
*/5 * * * * php /path/to/CNTrack/cron-track.php >> /dev/null 2>&1
```

Logs are written to `data/cron.log`. Can also be run manually:

```bash
php cron-track.php
```

## Project Structure

```
CNTrack/
├── index.php              # Front controller & routes
├── bootstrap.php          # Session, DB, error handlers, migrations
├── autoload.php           # PSR-4 class autoloader
├── config.php             # App configuration (gitignored)
├── config.example.php     # Configuration template
├── cron-track.php         # Cron job for auto-tracking
├── php-router.php         # PHP built-in server entry point
├── install.php            # Database installer (delete after use)
│
├── src/
│   ├── Core/              # Framework: Router, Database, Request, View
│   ├── Controllers/       # Auth, Dashboard, Container, Shipment, DeliveryPlanner, Analysis, Admin, Api
│   ├── Models/            # User, Container, TrackingHistory, Settings, Shipment, DeliveryPlan
│   └── Services/          # TrackingService (CN API + Google Sheets)
│
├── views/                 # PHP templates
│   ├── layout/            # Header, footer, main layout
│   ├── auth/              # Login
│   ├── dashboard/         # Container list, detail, quick track
│   ├── shipments/         # CRUD for shipments
│   ├── delivery_planner/  # Delivery scheduling with detention calc
│   ├── analysis/          # Statistics
│   ├── admin/             # User & settings management
│   └── errors/            # 403, 404 pages
│
├── helpers/               # cn_api.php, container_utils.php, Debug.php, google_sheet.php
├── assets/css/            # app.css (CN Red theme)
├── assets/js/             # app.js, delivery_planner.js
├── db/schema.sql          # Database schema reference
└── data/                  # Runtime: SQLite DB, logs (gitignored)
```

## Configuration

| Setting | Description |
|---------|-------------|
| `DEBUG_MODE` | Show detailed errors (disable in production) |
| `DB_PATH` | Path to SQLite database file |
| `TIMEZONE` | Application timezone |
| `DEFAULT_CN_API_KEY` | CN Rail API key |
| `DEFAULT_CN_AUTH_KEY` | CN Rail auth key |

Additional settings are configurable via the Admin > Settings page in the app.

## URL Routes

| Method | Path | Description |
|--------|------|-------------|
| GET | `/login` | Login page |
| POST | `/login` | Authenticate with PIN |
| GET | `/` | Dashboard — container list |
| POST | `/track` | Refresh tracking for containers |
| POST | `/track-single` | Refresh single container |
| GET | `/container?id=N` | Container detail view |
| GET | `/quick-track` | Quick track by container number |
| GET | `/shipments` | List shipments |
| GET | `/shipments/view?id=N` | Shipment detail |
| GET | `/delivery-planner` | Delivery planner |
| GET | `/analysis` | Analysis dashboard |
| GET | `/admin` | User management (admin) |
| GET | `/admin/settings` | App settings (admin) |

## License

MIT
