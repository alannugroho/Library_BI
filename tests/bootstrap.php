<?php

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Test environment bootstrap
|--------------------------------------------------------------------------
|
| Laragon exports APP_ENV and the DB_* variables into the real process
| environment ($_SERVER / getenv). Laravel's env repository is immutable and
| checks $_SERVER before $_ENV, so phpunit.xml's <env> entries alone cannot
| override them. Set every test variable directly here, before Laravel boots,
| to guarantee the suite always runs on the in-memory SQLite database.
|
*/

$variables = [
    'APP_ENV' => 'testing',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'BCRYPT_ROUNDS' => '4',
    'BROADCAST_CONNECTION' => 'null',
    'CACHE_STORE' => 'array',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'PULSE_ENABLED' => 'false',
    'TELESCOPE_ENABLED' => 'false',
    'NIGHTWATCH_ENABLED' => 'false',
];

foreach ($variables as $name => $value) {
    putenv("{$name}={$value}");
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}
