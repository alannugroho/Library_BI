<?php

declare(strict_types=1);

function render_view(string $view, array $data = []): never
{
    extract($data, EXTR_SKIP);
    require dirname(__DIR__) . '/views/' . ltrim($view, '/') . '.php';
    exit;
}

function require_authenticated(): void
{
    if (!isset($_SESSION['user'])) {
        flash('error', 'Silakan masuk terlebih dahulu.');
        legacy_redirect('/login');
    }
}

function require_role(string|array $roles): void
{
    require_authenticated();

    $allowedRoles = (array) $roles;
    if (!in_array($_SESSION['user']['role'], $allowedRoles, true)) {
        flash('error', 'Anda tidak memiliki akses ke halaman tersebut.');
        legacy_redirect('/dashboard');
    }
}

function current_user_id(): int
{
    require_authenticated();
    return (int) $_SESSION['user']['id'];
}

