<?php

declare(strict_types=1);

function handle_dashboard_route(string $path, string $method): bool
{
    if ($path !== '/dashboard' || $method !== 'GET') {
        return false;
    }

    require_authenticated();
    $pageTitle = 'Dashboard';
    $flash = consume_flash();
    $reservations = [];
    $circulationSummary = null;
    $recentCirculations = [];
    $activeReservations = [];
    $pendingMembers = 0;
    $pendingProposals = 0;
    try {
        $reservationStatement = database()->prepare(
            "SELECT r.reservation_code, r.status, r.created_at, cb.title
             FROM reservations r
             INNER JOIN catalog_books cb ON cb.id = r.catalog_id
             WHERE r.user_id = :user_id
             ORDER BY r.created_at DESC
             LIMIT 20"
        );
        $reservationStatement->execute(['user_id' => $_SESSION['user']['id']]);
        $reservations = $reservationStatement->fetchAll();
        if ($_SESSION['user']['role'] === 'pustakawan') {
            $summaryStatement = database()->query(
                "SELECT
                    COUNT(CASE WHEN status IN ('active', 'overdue') THEN 1 END) AS active_loans,
                    COUNT(CASE WHEN status = 'overdue' OR (status = 'active' AND due_date < CURDATE()) THEN 1 END) AS overdue_loans,
                    COUNT(CASE WHEN status = 'returned' AND return_date = CURDATE() THEN 1 END) AS returned_today
                 FROM circulations"
            );
            $circulationSummary = $summaryStatement->fetch();
            $recentStatement = database()->query(
                "SELECT c.borrow_date, c.due_date, c.return_date, c.status, c.fine_amount,
                        u.email, bi.barcode, cb.title
                 FROM circulations c
                 INNER JOIN users u ON u.id = c.user_id
                 INNER JOIN book_items bi ON bi.id = c.book_item_id
                 INNER JOIN catalog_books cb ON cb.id = bi.catalog_id
                 ORDER BY c.id DESC
                 LIMIT 10"
            );
            $recentCirculations = $recentStatement->fetchAll();
            $activeReservationStatement = database()->query(
                "SELECT r.reservation_code, r.status, r.created_at,
                        u.email, cb.title
                 FROM reservations r
                 INNER JOIN users u ON u.id = r.user_id
                 INNER JOIN catalog_books cb ON cb.id = r.catalog_id
                 WHERE r.status IN ('pending_pickup', 'waiting_list')
                 ORDER BY
                    CASE WHEN r.status = 'pending_pickup' THEN 0 ELSE 1 END,
                    r.created_at ASC
                 LIMIT 100"
            );
            $activeReservations = $activeReservationStatement->fetchAll();
            $pendingMembers = (int) database()->query("SELECT COUNT(*) FROM users WHERE role = 'eksternal' AND status = 'pending'")->fetchColumn();
            $pendingProposals = (int) database()->query("SELECT COUNT(*) FROM book_proposals WHERE status = 'submitted'")->fetchColumn();
        }
    } catch (PDOException $exception) {
        error_log('Dashboard reservation query failed: ' . $exception->getMessage());
    }

    render_view('dashboard', compact(
        'pageTitle',
        'flash',
        'reservations',
        'circulationSummary',
        'recentCirculations',
        'activeReservations',
        'pendingMembers',
        'pendingProposals'
    ));
}

