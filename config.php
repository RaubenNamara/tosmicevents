<?php
/**
 * Tosmic Events - configuration.
 * On a live server, change the database details below (or create config.local.php
 * that returns an array with the same keys to override them without editing this file).
 */
$config = [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'tosmicevents',
    'db_user' => 'root',
    'db_pass' => '',

    // Leave empty to detect automatically (works in a sub-folder like /tosmicevents or at the domain root).
    'base_url' => '',

    'timezone' => 'Africa/Kampala',
    // 'auto' = show PHP errors only on this computer (localhost); visitors on the live site never see them.
    // Errors are always written to storage/logs/php-errors.log (blocked from the web).
    'debug' => 'auto',
];

if (is_file(__DIR__ . '/config.local.php')) {
    $config = array_merge($config, (array) require __DIR__ . '/config.local.php');
}

return $config;
