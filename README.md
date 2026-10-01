# Digital Library BI

Digital Library BI is a Laravel 13 application for managing physical and
digital library collections, reservations, circulation, news clippings, and
e-resources.

## Requirements

- PHP 8.3+
- Composer
- MySQL/MariaDB for the shared application database
- Node.js and npm for frontend asset builds

## Local setup

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
npm install
npm run build
php artisan serve
```

Configure the database connection in `.env` before running migrations. Do not
run `php artisan migrate:fresh` against the shared development database.

## Main roles

- `anggota`: browse the catalog, reserve books, submit collection proposals,
  and use approved e-resources.
- `pustakawan`: manage members, catalog items, proposals, news, e-resources,
  circulation, overdue maintenance, and reports.
- `eksternal`: register and wait for email verification and librarian approval.

## File storage

Uploaded digital books and news clippings are stored on Laravel's `public`
filesystem disk under `storage/app/public/uploads`. Run
`php artisan storage:link` so public storage assets are available.

## Testing

```powershell
php artisan test
```

The test suite covers application bootstrap, native route registration,
authentication boundaries, CSRF protection, and registration validation.

## Migration status

All web routes run through Laravel controllers, middleware, and Blade views.
The former legacy application has been removed. See `MIGRATION.md` for the
migration details.
