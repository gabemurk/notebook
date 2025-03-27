<?php
// Database configuration
$DB_CONFIG = [
    'postgresql' => [
        'host' => '127.0.0.1', // Use direct IP instead of localhost to avoid IPv6 issues
        'port' => 5432,
        'dbname' => 'notebook',
        'user' => 'notebook_user',
        'password' => 'notebook_password'
    ],
    'sqlite' => [
        'path' => __DIR__ . '/notebook.sqlite'
    ]
];

// Create SQLite database if it doesn't exist
if (!file_exists($DB_CONFIG['sqlite']['path'])) {
    $sqlite = new SQLite3($DB_CONFIG['sqlite']['path']);
    $sqlite->close();
}
