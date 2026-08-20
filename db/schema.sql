-- CN Track - Database Schema (SQLite)

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    pin TEXT NOT NULL UNIQUE,
    role TEXT NOT NULL DEFAULT 'user',
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS containers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    container_number TEXT NOT NULL,
    bill_of_lading TEXT DEFAULT '',
    po_number TEXT DEFAULT '',
    customer_name TEXT DEFAULT '',
    destination TEXT DEFAULT '',
    commodity TEXT DEFAULT '',
    notes TEXT DEFAULT '',
    tags TEXT DEFAULT '',
    is_archived INTEGER NOT NULL DEFAULT 0,
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_checked_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(user_id, container_number)
);

CREATE TABLE IF NOT EXISTS tracking_snapshots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    container_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    container_number TEXT NOT NULL,
    waybill_status TEXT,
    load_empty TEXT,
    last_event TEXT,
    last_event_time_local TEXT,
    last_event_timezone TEXT,
    last_event_location TEXT,
    eta_local TEXT,
    eta_timezone TEXT,
    eta_station TEXT,
    last_free_day TEXT,
    customs_status TEXT,
    customs_timestamp TEXT,
    gps_latitude REAL,
    gps_longitude REAL,
    gps_speed TEXT,
    raw_api_response TEXT,
    checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS app_settings (
    key TEXT PRIMARY KEY,
    value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Shipments table
CREATE TABLE IF NOT EXISTS shipments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT DEFAULT '',
    bill_of_lading TEXT DEFAULT '',
    po_number TEXT DEFAULT '',
    customer_name TEXT DEFAULT '',
    destination TEXT DEFAULT '',
    commodity TEXT DEFAULT '',
    notes TEXT DEFAULT '',
    tags TEXT DEFAULT '',
    is_archived INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Delivery Plans table
CREATE TABLE IF NOT EXISTS delivery_plans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    container_id INTEGER NOT NULL,
    shipment_id INTEGER DEFAULT NULL,
    delivery_date TEXT,
    delivery_time TEXT,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE CASCADE,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE SET NULL,
    UNIQUE(user_id, container_id)
);

-- Add shipment_id to containers if missing (migration in init.php handles it)
-- ALTER TABLE containers ADD COLUMN shipment_id INTEGER DEFAULT NULL REFERENCES shipments(id) ON DELETE SET NULL;

-- Indexes
CREATE INDEX IF NOT EXISTS idx_containers_user ON containers(user_id, is_archived);
CREATE INDEX IF NOT EXISTS idx_snapshots_container ON tracking_snapshots(container_id, checked_at DESC);
CREATE INDEX IF NOT EXISTS idx_snapshots_user ON tracking_snapshots(user_id);
CREATE INDEX IF NOT EXISTS idx_shipments_user ON shipments(user_id, is_archived);
CREATE INDEX IF NOT EXISTS idx_delivery_user ON delivery_plans(user_id);

-- Default settings
INSERT OR IGNORE INTO app_settings (key, value) VALUES ('cn_api_key', 'LMHDqRAksRN3W9WSBufgMKsCvbjg6dJo');
INSERT OR IGNORE INTO app_settings (key, value) VALUES ('cn_auth_key', 'kS3tVxUEihcvio56');
INSERT OR IGNORE INTO app_settings (key, value) VALUES ('auto_refresh', '0');
INSERT OR IGNORE INTO app_settings (key, value) VALUES ('timezone', 'America/Toronto');
