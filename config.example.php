<?php
/**
 * CN Track - Configuration
 * Copy this file to config.php and fill in your values.
 */

define('APP_NAME', 'CN Track');
define('APP_VERSION', '1.0.0');
define('APP_DIR', __DIR__);

// Debug Mode - SET TO FALSE IN PRODUCTION
define('DEBUG_MODE', false);

// Database
define('DB_PATH', APP_DIR . '/data/cntrack.db');

// Timezone
define('TIMEZONE', 'America/Toronto');

// CN Rail API (get from your CN Rail developer account)
define('DEFAULT_CN_API_KEY', 'YOUR_CN_API_KEY_HERE');
define('DEFAULT_CN_AUTH_KEY', 'YOUR_CN_AUTH_KEY_HERE');
define('CN_AUTH_URL', 'https://api.cn.ca/v1/oauth/jwt-token/accesstokenJWT?grant_type=client_credentials');
define('CN_TRACKING_URL', 'https://api.cn.ca/customers/v1/shipments/tracking');
define('CN_GPS_URL', 'https://api.cn.ca/customers/v1/gpslocation');

// API Batch Limits
define('API_TRACKING_BATCH_SIZE', 20);
define('API_GPS_BATCH_SIZE', 100);

// History
define('MAX_SNAPSHOTS_PER_CONTAINER', 50);

// Debug log path
define('DEBUG_LOG_PATH', APP_DIR . '/data/debug.log');
