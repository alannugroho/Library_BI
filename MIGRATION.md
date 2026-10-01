# Laravel migration status

The project now boots through Laravel 13 and uses Laravel's `.env`,
Composer, Artisan, routing, middleware pipeline, logging, and test runner.

All application routes now run through Laravel controllers, middleware, and
Blade views. The legacy catch-all entry point has been removed, so unknown
URLs correctly return Laravel's 404 response instead of executing the old
front controller.

The current database is already compatible with the library schema. The
baseline migration `2026_09_30_000000_baseline_digital_library_schema.php`
is intentionally a no-op when the `users` table exists. On a fresh database it
loads the normalized schema from `database/schema.sql`.

Uploaded digital books and news clippings are stored on Laravel's `public`
filesystem disk under `storage/app/public/uploads`.

Useful commands:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
php artisan route:list
php artisan test
```

Do not run `migrate:fresh` against the shared development database. The
historical `legacy/` directory has been removed after the native Laravel
routes and storage paths were verified.
