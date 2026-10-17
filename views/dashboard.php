<?php
/**
 * Kalamedia Dashboard View Router / Proxy
 */
$context = resolveActiveSessionContext();
if (is_owner($context)) {
    require __DIR__ . '/owner_dashboard.php';
} else {
    require __DIR__ . '/admin_dashboard.php';
}
