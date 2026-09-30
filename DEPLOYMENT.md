# Deployment checklist

## Application configuration

For local development, copy `.env.example` to `.env` in the project root and
adjust the values for your machine. The application loads `.env` automatically
when it exists. Do not commit `.env`; it is excluded by `.gitignore`.

For production, set these environment variables in Apache/PHP before starting
the application instead of storing secrets in the project directory:

```text
APP_ENV=production
APP_BASE_PATH=
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=digital_library_bi
DB_USER=<dedicated-database-user>
DB_PASSWORD=<strong-password>
```

Use a dedicated MySQL user with access only to `digital_library_bi`; do not use
the root account in production. The local demo passwords in
`database/seed_demo.sql` are for development only and must not be deployed.

## Apache and files

Point the virtual host document root to `public`, not the project root. Enable
`mod_rewrite`, `AllowOverride FileInfo Limit`, and HTTPS. Keep
`storage/uploads` writable by PHP but outside the public document root. The
included `.htaccess` disables directory listing and PHP-like execution in the
upload directory.

This application is now bootstrapped by Laravel. Use `php artisan` for
configuration, migrations, cache management, and tests. Existing business
routes currently run through the temporary `legacy/` compatibility layer while
they are being converted to native Laravel controllers and Blade views.

## Database and backup

Run `database/schema.sql` once, then load `database/seed_demo.sql` only in a
local environment. Back up the database before schema changes and verify that
both database and uploaded files can be restored.

## Verification

Run the following from the project root after deployment:

```powershell
php -l public\index.php
.\tests\smoke.ps1 -BaseUrl https://library.example
```

Confirm that HTTPS is active, `/storage/uploads` is not directly browsable,
unauthenticated users cannot access `/digital`, and production registration
does not expose a local verification URL.
