#!/usr/bin/env php
<?php

/**
 * Database Migration CLI.
 *
 * Usage:
 *   php bin/migrate.php up       — Run all pending migrations
 *   php bin/migrate.php down     — Roll back the last migration
 *   php bin/migrate.php status   — Show migration status
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\Config;
use App\Services\Migrator;

Config::boot();

$command = $argv[1] ?? 'status';
$migrator = new Migrator();

match ($command) {
    'up'     => $migrator->up(),
    'down'   => $migrator->down(),
    'status' => $migrator->status(),
    default  => print("Usage: php bin/migrate.php [up|down|status]\n"),
};
