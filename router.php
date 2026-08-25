<?php
/**
 * Kalamedia Development Server Router for PHP Built-in Web Server
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve static assets and existing physical files directly
if ($uri !== '/' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    return false;
}

// Route front controller requests to index.php
require_once __DIR__ . '/index.php';
