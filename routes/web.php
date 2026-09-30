<?php

use App\Http\Controllers\LegacyController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\AdminCatalogController;
use App\Http\Controllers\CirculationController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::get('/', [HomeController::class, 'index']);
Route::get('/digital-collections', fn () => redirect('/catalog?type=digital'));
Route::get('/reservations', fn () => redirect('/dashboard'))->middleware('auth');
Route::post('/login', [AuthController::class, 'store']);
Route::get('/register', [RegistrationController::class, 'create']);
Route::post('/register', [RegistrationController::class, 'store']);
Route::get('/verify-email', [RegistrationController::class, 'verify']);
Route::get('/proposals', [ProposalController::class, 'index'])->middleware('auth');
Route::post('/proposals', [ProposalController::class, 'store'])->middleware('auth');
Route::post('/reserve', [ReservationController::class, 'store'])->defaults('mode', 'reserve')->middleware('auth');
Route::post('/waitlist', [ReservationController::class, 'store'])->defaults('mode', 'waitlist')->middleware('auth');
Route::get('/admin/members', [AdminController::class, 'members'])->middleware('auth');
Route::post('/admin/members', [AdminController::class, 'updateMember'])->middleware('auth');
Route::get('/admin/proposals', [AdminController::class, 'proposals'])->middleware('auth');
Route::post('/admin/proposals', [AdminController::class, 'updateProposal'])->middleware('auth');
Route::get('/news', [ContentController::class, 'news']);
Route::get('/news/file', fn (Request $request, ContentController $controller) => $controller->newsFile($request->integer('id')));
Route::get('/e-resources', [ContentController::class, 'eResources']);
Route::get('/e-resources/go', fn (Request $request, ContentController $controller) => $controller->resourceGo($request->integer('id')))->middleware('auth');
Route::get('/digital', fn (Request $request, ContentController $controller) => $controller->digital($request->integer('id')))->middleware('auth');
Route::get('/admin/e-resources', [ContentController::class, 'adminResources'])->middleware('auth');
Route::post('/admin/e-resources', [ContentController::class, 'saveResource'])->middleware('auth');
Route::post('/admin/e-resources/delete', [ContentController::class, 'deleteResource'])->middleware('auth');
Route::get('/admin/news', [ContentController::class, 'adminNews'])->middleware('auth');
Route::post('/admin/news', [ContentController::class, 'saveNews'])->middleware('auth');
Route::get('/admin/reports', [ContentController::class, 'reports'])->middleware('auth');
Route::get('/admin/catalog', [AdminCatalogController::class, 'index'])->middleware('auth');
Route::post('/admin/catalog', [AdminCatalogController::class, 'store'])->middleware('auth');
Route::post('/circulation', [CirculationController::class, 'store'])->middleware('auth');
Route::post('/admin/maintenance/overdue', [CirculationController::class, 'overdue'])->middleware('auth');
Route::post('/logout', [AuthController::class, 'destroy'])
    ->middleware('auth')
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
Route::get('/catalog', [CatalogController::class, 'index']);
Route::get('/search', [CatalogController::class, 'index']);
Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('auth');
Route::any('/{any?}', [LegacyController::class, 'handle'])
    ->where('any', '.*')
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
    ]);
