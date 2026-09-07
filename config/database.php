<?php

return [
    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('MYSQL_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'rachaqakost'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'postgres'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'require'),
        ],

        // Read-only source used by the one-time PostgreSQL -> MariaDB migration.
        'source_pgsql' => [
            'driver' => 'pgsql',
            'url' => env('SOURCE_DATABASE_URL'),
            'host' => env('SOURCE_DB_HOST', '127.0.0.1'),
            'port' => env('SOURCE_DB_PORT', '5432'),
            'database' => env('SOURCE_DB_DATABASE', 'postgres'),
            'username' => env('SOURCE_DB_USERNAME', 'postgres'),
            'password' => env('SOURCE_DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('SOURCE_DB_SSLMODE', 'require'),
        ],
    ],

    'migrations' => ['table' => 'migrations'],
];
