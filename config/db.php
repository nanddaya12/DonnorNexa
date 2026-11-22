<?php
/**
 * Database connection. Defaults match a fresh XAMPP install.
 * Environment variables override the defaults if you need them.
 */
return [
    'host'    => getenv('DN_DB_HOST') ?: '127.0.0.1',
    'port'    => (int) (getenv('DN_DB_PORT') ?: 3306),
    'name'    => getenv('DN_DB_NAME') ?: 'donornexa',
    'user'    => getenv('DN_DB_USER') ?: 'root',
    'pass'    => getenv('DN_DB_PASS') !== false ? getenv('DN_DB_PASS') : '',
    'charset' => 'utf8mb4',
];
