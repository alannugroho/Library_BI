<?php

declare(strict_types=1);

function handle_catalog_route(string $path, string $method): bool
{
    if (!in_array($path, ['/catalog', '/search'], true) || $method !== 'GET') {
        return false;
    }

    $query = trim((string) ($_GET['q'] ?? ''));
    $type = (string) ($_GET['type'] ?? '');
    $books = [];
    $userReservations = [];
    $databaseError = null;
    $flash = consume_flash();

    try {
        $conditions = [];
        $parameters = [];
        if ($query !== '') {
            $conditions[] = '(cb.title LIKE :query_title OR cb.author LIKE :query_author OR cb.publisher LIKE :query_publisher)';
            $parameters['query_title'] = '%' . $query . '%';
            $parameters['query_author'] = '%' . $query . '%';
            $parameters['query_publisher'] = '%' . $query . '%';
        }
        if (in_array($type, ['physical', 'digital'], true)) {
            $conditions[] = 'cb.type = :type';
            $parameters['type'] = $type;
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $statement = database()->prepare(
            "SELECT cb.id, cb.title, cb.author, cb.publisher, cb.publication_year, cb.type,
                    cb.digital_file_path,
                    COUNT(CASE WHEN bi.status = 'available' THEN 1 END) AS available_stock
             FROM catalog_books cb
             LEFT JOIN book_items bi ON bi.catalog_id = cb.id
             $where
             GROUP BY cb.id
             ORDER BY cb.title ASC
             LIMIT 100"
        );
        $statement->execute($parameters);
        $books = $statement->fetchAll();

        if (isset($_SESSION['user'])) {
            $reservationStatement = database()->prepare(
                "SELECT catalog_id, status, reservation_code
                 FROM reservations
                 WHERE user_id = :user_id
                   AND status IN ('pending_pickup', 'waiting_list')"
            );
            $reservationStatement->execute(['user_id' => $_SESSION['user']['id']]);
            foreach ($reservationStatement->fetchAll() as $reservation) {
                $userReservations[(int) $reservation['catalog_id']] = $reservation;
            }
        }
    } catch (PDOException $exception) {
        error_log('Catalog query failed: ' . $exception->getMessage());
        $databaseError = 'Katalog belum dapat dimuat. Pastikan database sudah dibuat dari database/schema.sql.';
    }

    $pageTitle = $query !== ''
        ? 'Hasil pencarian: ' . $query
        : ($type === 'digital' ? 'Koleksi digital' : 'Katalog koleksi');
    render_view('catalog', compact('pageTitle', 'query', 'type', 'books', 'userReservations', 'databaseError', 'flash'));
}

