<?php
// LKPlay Panel Configuration
// Configure these values before deploying

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'lkplay_panel');
define('DB_USER', 'lkplay_user');
define('DB_PASS', 'CHANGE_THIS_SECURE_PASSWORD');

// Admin Credentials
// Default: admin / admin123 (CHANGE ON FIRST LOGIN!)
define('ADMIN_USER', 'admin');
// Password hash for 'admin123' - GENERATE NEW ONE WITH: password_hash('your_password', PASSWORD_DEFAULT)
define('ADMIN_PASS_HASH', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); // admin123

// Security
define('SESSION_LIFETIME', 3600); // 1 hour

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Error Reporting (disable in production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);