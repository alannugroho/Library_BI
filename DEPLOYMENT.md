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
`mod_rewrite`, `AllowOverride FileInfo Limit`, and HTTPS. Run
`php artisan storage:link` and keep `storage/app/public` writable by PHP.

This application is bootstrapped by Laravel. Use `php artisan` for
configuration, migrations, route/view cache management, storage links, and
tests. All business routes use native Laravel controllers and Blade views.

## Database and backup

Run `php artisan migrate` for the schema, then load `database/seed_demo.sql`
only in a local environment. Back up the database and
`storage/app/public/uploads` before schema or file-storage changes, and verify
that both can be restored.

## Verification

Run the following from the project root after deployment:

```powershell
php artisan migrate:status
php artisan optimize
php artisan storage:link
php artisan test
```

Confirm that HTTPS is active, `/storage/uploads` is not directly browsable,
unauthenticated users cannot access `/digital`, and production registration
does not expose a local verification URL. Confirm that production uses
`APP_ENV=production` and never loads `database/seed_demo.sql`.
