# Laravel migration status

The project now boots through Laravel 13 and uses Laravel's `.env`,
Composer, Artisan, routing, middleware pipeline, logging, and test runner.

The existing application is temporarily isolated under `legacy/` and is
invoked through `App\Http\Controllers\LegacyController`. This compatibility
layer preserves all existing URLs and business behavior while the controllers,
models, Blade views, and form requests are migrated incrementally.

The current database is already compatible with the library schema. The
baseline migration `2026_09_30_000000_baseline_digital_library_schema.php`
is intentionally a no-op when the `users` table exists. On a fresh database it
loads the normalized schema from `legacy/database/schema.sql`.

Useful commands:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
php artisan route:list
php artisan test
```

Do not run `migrate:fresh` against the shared development database. The legacy
PowerShell smoke suite remains available at `legacy/tests/smoke.ps1` until the
remaining compatibility routes are converted to native Laravel feature tests.
