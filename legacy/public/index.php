<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$basePath = rtrim(app_config()['app']['base_path'], '/');

if ($basePath !== '' && str_starts_with($path, $basePath)) {
    $path = substr($path, strlen($basePath)) ?: '/';
}

if (handle_auth_route($path, $method)) {
    exit;
}

if (handle_catalog_route($path, $method)) {
    exit;
}

if (handle_dashboard_route($path, $method)) {
    exit;
}

if ($path === '/digital-collections' && $method === 'GET') {
    legacy_redirect('/catalog?type=digital');
}

if ($path === '/reservations' && $method === 'GET') {
    require_authenticated();
    legacy_redirect('/dashboard');
}

if ($path === '/' && $method === 'GET') {
    $pageTitle = app_config()['app']['name'];
    render_view('home', compact('pageTitle'));
}

if ($path === '/admin/catalog' && $method === 'GET') {
    require_role('pustakawan');

    $editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
    $editBook = null;
    $books = [];
    $items = [];
    $flash = consume_flash();
    try {
        $books = database()->query(
            "SELECT cb.id, cb.title, cb.author, cb.publisher, cb.publication_year, cb.isbn,
                    cb.udc_classification, cb.type, cb.digital_file_path,
                    COUNT(bi.id) AS item_count,
                    COUNT(CASE WHEN bi.status = 'available' THEN 1 END) AS available_stock
             FROM catalog_books cb
             LEFT JOIN book_items bi ON bi.catalog_id = cb.id
             GROUP BY cb.id
             ORDER BY cb.updated_at DESC"
        )->fetchAll();
        if ($editId) {
            $editStatement = database()->prepare('SELECT * FROM catalog_books WHERE id = :id');
            $editStatement->execute(['id' => $editId]);
            $editBook = $editStatement->fetch() ?: null;
            if ($editBook) {
                $itemStatement = database()->prepare(
                    'SELECT id, barcode, shelf_location, status FROM book_items WHERE catalog_id = :catalog_id ORDER BY id DESC'
                );
                $itemStatement->execute(['catalog_id' => $editId]);
                $items = $itemStatement->fetchAll();
            }
        }
    } catch (PDOException $exception) {
        error_log('Catalog admin query failed: ' . $exception->getMessage());
        $flash = ['type' => 'error', 'message' => 'Data katalog belum dapat dimuat.'];
    }
    $pageTitle = 'Kelola katalog';
    require dirname(__DIR__) . '/views/admin/catalog.php';
    exit;
}

if ($path === '/admin/catalog' && $method === 'POST') {
    require_role('pustakawan');
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('error', 'Sesi formulir kedaluwarsa. Silakan coba lagi.');
        legacy_redirect('/admin/catalog');
    }

    $action = (string) ($_POST['action'] ?? '');
    $catalogId = filter_var($_POST['catalog_id'] ?? null, FILTER_VALIDATE_INT);
    $pdo = null;
    try {
        $pdo = database();
        $pdo->beginTransaction();

        if ($action === 'delete') {
            if ($catalogId === false || $catalogId < 1) {
                throw new RuntimeException('Koleksi tidak valid.');
            }
            $check = $pdo->prepare(
                "SELECT COUNT(*) FROM circulations c
                 INNER JOIN book_items bi ON bi.id = c.book_item_id
                 WHERE bi.catalog_id = :catalog_id AND c.status IN ('active', 'overdue')"
            );
            $check->execute(['catalog_id' => $catalogId]);
            if ((int) $check->fetchColumn() > 0) {
                throw new RuntimeException('Koleksi tidak dapat dihapus karena masih dipinjam.');
            }
            $pdo->prepare('DELETE FROM catalog_books WHERE id = :id')->execute(['id' => $catalogId]);
            $message = 'Koleksi berhasil dihapus.';
        } elseif ($action === 'save') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $type = (string) ($_POST['type'] ?? '');
            $year = filter_var($_POST['publication_year'] ?? null, FILTER_VALIDATE_INT);
            if ($title === '' || !in_array($type, ['physical', 'digital'], true)) {
                throw new RuntimeException('Judul dan jenis koleksi wajib diisi.');
            }
            if ($year !== false && ($year < 1000 || $year > ((int) date('Y') + 1))) {
                throw new RuntimeException('Tahun terbit tidak valid.');
            }

            $digitalPath = null;
            $uploadedFile = $_FILES['digital_file'] ?? null;
            if ($type === 'digital' && is_array($uploadedFile) && ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if (($uploadedFile['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK
                    || !is_uploaded_file((string) ($uploadedFile['tmp_name'] ?? ''))
                    || ($uploadedFile['size'] ?? 0) <= 0
                    || ($uploadedFile['size'] ?? 0) > 20 * 1024 * 1024) {
                    throw new RuntimeException('File digital harus berukuran maksimal 20 MB.');
                }
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($uploadedFile['tmp_name']);
                if ($mime !== 'application/pdf') {
                    throw new RuntimeException('File digital harus berupa PDF.');
                }
                $storagePath = app_config()['app']['storage_path'];
                if (!is_dir($storagePath) && !mkdir($storagePath, 0750, true) && !is_dir($storagePath)) {
                    throw new RuntimeException('Penyimpanan file tidak dapat dibuat.');
                }
                $storedName = bin2hex(random_bytes(16)) . '.pdf';
                if (!move_uploaded_file($uploadedFile['tmp_name'], $storagePath . DIRECTORY_SEPARATOR . $storedName)) {
                    throw new RuntimeException('File digital gagal disimpan.');
                }
                $digitalPath = $storedName;
            }

            $fields = [
                'title' => $title,
                'author' => trim((string) ($_POST['author'] ?? '')) ?: null,
                'publisher' => trim((string) ($_POST['publisher'] ?? '')) ?: null,
                'publication_year' => $year === false ? null : $year,
                'isbn' => trim((string) ($_POST['isbn'] ?? '')) ?: null,
                'udc_classification' => trim((string) ($_POST['udc_classification'] ?? '')) ?: null,
                'type' => $type,
            ];
            if ($catalogId !== false && $catalogId > 0) {
                $sql = 'UPDATE catalog_books SET title=:title, author=:author, publisher=:publisher,
                        publication_year=:publication_year, isbn=:isbn, udc_classification=:udc_classification, type=:type';
                if ($digitalPath !== null) {
                    $sql .= ', digital_file_path=:digital_file_path';
                    $fields['digital_file_path'] = $digitalPath;
                }
                $sql .= ' WHERE id=:id';
                $fields['id'] = $catalogId;
                $pdo->prepare($sql)->execute($fields);
                $message = 'Koleksi berhasil diperbarui.';
            } else {
                if ($type === 'digital' && $digitalPath === null) {
                    throw new RuntimeException('Koleksi digital harus memiliki file PDF.');
                }
                $fields['digital_file_path'] = $digitalPath;
                $pdo->prepare(
                    'INSERT INTO catalog_books (title, author, publisher, publication_year, isbn, udc_classification, type, digital_file_path)
                     VALUES (:title, :author, :publisher, :publication_year, :isbn, :udc_classification, :type, :digital_file_path)'
                )->execute($fields);
                $catalogId = (int) $pdo->lastInsertId();
                $message = 'Koleksi berhasil ditambahkan.';
            }
        } elseif ($action === 'item') {
            if ($catalogId === false || $catalogId < 1) {
                throw new RuntimeException('Koleksi tidak valid.');
            }
            $barcode = trim((string) ($_POST['barcode'] ?? ''));
            $shelf = trim((string) ($_POST['shelf_location'] ?? ''));
            if ($barcode === '') {
                throw new RuntimeException('Barcode wajib diisi.');
            }
            $pdo->prepare(
                'INSERT INTO book_items (catalog_id, barcode, shelf_location) VALUES (:catalog_id, :barcode, :shelf_location)'
            )->execute([
                'catalog_id' => $catalogId,
                'barcode' => $barcode,
                'shelf_location' => $shelf ?: null,
            ]);
            $message = 'Salinan fisik berhasil ditambahkan.';
        } elseif ($action === 'item-delete') {
            $itemId = filter_var($_POST['item_id'] ?? null, FILTER_VALIDATE_INT);
            if ($itemId === false || $itemId < 1) {
                throw new RuntimeException('Salinan tidak valid.');
            }
            $itemStatement = $pdo->prepare("SELECT status FROM book_items WHERE id = :id FOR UPDATE");
            $itemStatement->execute(['id' => $itemId]);
            $item = $itemStatement->fetch();
            if (!$item || $item['status'] !== 'available') {
                throw new RuntimeException('Hanya salinan tersedia yang dapat dihapus.');
            }
            $pdo->prepare('DELETE FROM book_items WHERE id = :id')->execute(['id' => $itemId]);
            $message = 'Salinan fisik berhasil dihapus.';
        } else {
            throw new RuntimeException('Tindakan katalog tidak dikenal.');
        }

        $pdo->commit();
        flash('success', $message);
    } catch (RuntimeException $exception) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $exception->getMessage());
    } catch (PDOException $exception) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorCode = is_array($exception->errorInfo ?? null) ? (int) ($exception->errorInfo[1] ?? 0) : 0;
        flash('error', $errorCode === 1062 ? 'Barcode atau data unik tersebut sudah digunakan.' : 'Perubahan katalog gagal disimpan.');
        error_log('Catalog admin write failed: ' . $exception->getMessage());
    }
    legacy_redirect('/admin/catalog' . (($catalogId !== false && $catalogId > 0) ? '?edit=' . $catalogId : ''));
}

if ($path === '/digital' && $method === 'GET') {
    if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['anggota', 'eksternal', 'pustakawan'], true)) {
        http_response_code(403);
        exit('Akses koleksi digital memerlukan akun aktif.');
    }
    $catalogId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    if ($catalogId === false || $catalogId < 1) {
        http_response_code(404);
        exit('Koleksi digital tidak ditemukan.');
    }
    try {
        $statement = database()->prepare(
            "SELECT digital_file_path, type FROM catalog_books
             WHERE id = :id AND type = 'digital' LIMIT 1"
        );
        $statement->execute(['id' => $catalogId]);
        $book = $statement->fetch();
        $storagePath = app_config()['app']['storage_path'];
        $filePath = $book && $book['digital_file_path']
            ? $storagePath . DIRECTORY_SEPARATOR . basename($book['digital_file_path'])
            : '';
        if (!$book || $filePath === '' || !is_file($filePath)) {
            http_response_code(404);
            exit('File digital tidak ditemukan.');
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="digital-library-' . $catalogId . '.pdf"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . (string) filesize($filePath));
        readfile($filePath);
    } catch (PDOException $exception) {
        error_log('Digital asset query failed: ' . $exception->getMessage());
        http_response_code(500);
        exit('File digital belum dapat diakses.');
    }
    exit;
}

if ($path === '/proposals' && $method === 'GET') {
    require_role('anggota');
    $proposals = [];
    $flash = consume_flash();
    try {
        $statement = database()->prepare(
            'SELECT title, author, status, created_at FROM book_proposals WHERE user_id = :user_id ORDER BY created_at DESC'
        );
        $statement->execute(['user_id' => $_SESSION['user']['id']]);
        $proposals = $statement->fetchAll();
    } catch (PDOException $exception) {
        error_log('Proposal listing failed: ' . $exception->getMessage());
        $flash = ['type' => 'error', 'message' => 'Usulan koleksi belum dapat dimuat.'];
    }
    $pageTitle = 'Usulan koleksi';
    require dirname(__DIR__) . '/views/proposals.php';
    exit;
}

if ($path === '/proposals' && $method === 'POST') {
    require_role('anggota');
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('error', 'Sesi formulir kedaluwarsa. Silakan coba lagi.');
        legacy_redirect('/proposals');
    }
    $title = trim((string) ($_POST['title'] ?? ''));
    $author = trim((string) ($_POST['author'] ?? ''));
    if ($title === '' || mb_strlen($title) > 255 || mb_strlen($author) > 255) {
        flash('error', 'Judul wajib diisi dan panjang data tidak boleh melebihi 255 karakter.');
        legacy_redirect('/proposals');
    }
    try {
        $statement = database()->prepare(
            'INSERT INTO book_proposals (user_id, title, author) VALUES (:user_id, :title, :author)'
        );
        $statement->execute([
            'user_id' => $_SESSION['user']['id'],
            'title' => $title,
            'author' => $author ?: null,
        ]);
        flash('success', 'Usulan koleksi berhasil dikirim.');
    } catch (PDOException $exception) {
        error_log('Proposal creation failed: ' . $exception->getMessage());
        flash('error', 'Usulan koleksi gagal dikirim.');
    }
    legacy_redirect('/proposals');
}

if ($path === '/admin/members' && $method === 'GET') {
    require_role('pustakawan');
    $members = [];
    $flash = consume_flash();
    try {
        $members = database()->query(
            "SELECT id, nip, email, role, status, created_at
             FROM users
             ORDER BY CASE WHEN status = 'pending' THEN 0 ELSE 1 END, created_at DESC"
        )->fetchAll();
    } catch (PDOException $exception) {
        error_log('Member listing failed: ' . $exception->getMessage());
        $flash = ['type' => 'error', 'message' => 'Data anggota belum dapat dimuat.'];
    }
    $pageTitle = 'Kelola anggota';
    require dirname(__DIR__) . '/views/admin/members.php';
    exit;
}

if ($path === '/admin/members' && $method === 'POST') {
    require_role('pustakawan');
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('error', 'Sesi formulir kedaluwarsa. Silakan coba lagi.');
        legacy_redirect('/admin/members');
    }
    $memberId = filter_var($_POST['member_id'] ?? null, FILTER_VALIDATE_INT);
    $status = (string) ($_POST['status'] ?? '');
    if ($memberId === false || $memberId < 1 || !in_array($status, ['active', 'inactive', 'pending'], true)) {
        flash('error', 'Data anggota atau status tidak valid.');
        legacy_redirect('/admin/members');
    }
    try {
        $statement = database()->prepare(
            "UPDATE users SET status = :status
             WHERE id = :id AND role = 'eksternal' AND id <> :current_user_id"
        );
        $statement->execute([
            'status' => $status,
            'id' => $memberId,
            'current_user_id' => $_SESSION['user']['id'],
        ]);
        flash('success', $statement->rowCount() === 1 ? 'Status anggota berhasil diperbarui.' : 'Anggota tidak ditemukan atau tidak dapat diubah.');
    } catch (PDOException $exception) {
        error_log('Member status update failed: ' . $exception->getMessage());
        flash('error', 'Status anggota gagal diperbarui.');
    }
    legacy_redirect('/admin/members');
}

if ($path === '/admin/proposals' && $method === 'GET') {
    require_role('pustakawan');
    $proposals = [];
    $flash = consume_flash();
    try {
        $proposals = database()->query(
            "SELECT bp.id, bp.title, bp.author, bp.status, bp.created_at, u.email
             FROM book_proposals bp
             INNER JOIN users u ON u.id = bp.user_id
             ORDER BY CASE WHEN bp.status = 'submitted' THEN 0 ELSE 1 END, bp.created_at DESC"
        )->fetchAll();
    } catch (PDOException $exception) {
        error_log('Admin proposal listing failed: ' . $exception->getMessage());
        $flash = ['type' => 'error', 'message' => 'Usulan belum dapat dimuat.'];
    }
    $pageTitle = 'Tinjau usulan koleksi';
    require dirname(__DIR__) . '/views/admin/proposals.php';
    exit;
}

if ($path === '/admin/proposals' && $method === 'POST') {
    require_role('pustakawan');
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('error', 'Sesi formulir kedaluwarsa. Silakan coba lagi.');
        legacy_redirect('/admin/proposals');
    }

    $proposalId = filter_var($_POST['proposal_id'] ?? null, FILTER_VALIDATE_INT);
    $status = (string) ($_POST['status'] ?? '');
    if ($proposalId === false || $proposalId < 1 || !in_array($status, ['reviewed', 'approved', 'rejected'], true)) {
        flash('error', 'Usulan atau status tidak valid.');
        legacy_redirect('/admin/proposals');
    }
    try {
        $statement = database()->prepare(
            "UPDATE book_proposals SET status = :status WHERE id = :id AND status <> 'rejected'"
        );
        $statement->execute(['status' => $status, 'id' => $proposalId]);
        flash('success', $statement->rowCount() === 1 ? 'Status usulan berhasil diperbarui.' : 'Usulan tidak ditemukan atau sudah ditolak.');
    } catch (PDOException $exception) {
        error_log('Proposal review failed: ' . $exception->getMessage());
        flash('error', 'Status usulan gagal diperbarui.');
    }
    legacy_redirect('/admin/proposals');
}

if ($path === '/news' && $method === 'GET') {
        $query = trim((string) ($_GET['q'] ?? ''));
        $source = trim((string) ($_GET['source'] ?? ''));
        $clippings = [];
        $flash = consume_flash();
        try {
            $conditions = [];
            $parameters = [];
            if ($query !== '') {
                $conditions[] = '(title LIKE :title_query OR source_media LIKE :source_query)';
                $parameters['title_query'] = '%' . $query . '%';
                $parameters['source_query'] = '%' . $query . '%';
            }
            if ($source !== '') {
                $conditions[] = 'source_media = :source';
                $parameters['source'] = $source;
            }
            $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);
            $statement = database()->prepare(
                "SELECT id, title, source_media, publish_date
                 FROM news_clippings $where
                 ORDER BY publish_date DESC, id DESC LIMIT 100"
            );
            $statement->execute($parameters);
            $clippings = $statement->fetchAll();
        } catch (PDOException $exception) {
            error_log('News listing failed: ' . $exception->getMessage());
            $flash = ['type' => 'error', 'message' => 'News clippings belum dapat dimuat.'];
        }
        $pageTitle = 'News clippings';
        require dirname(__DIR__) . '/views/news.php';
        exit;
    }

    if ($path === '/news/file' && $method === 'GET') {
        $clippingId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if ($clippingId === false || $clippingId < 1) {
            http_response_code(404);
            exit('Clipping tidak ditemukan.');
        }
        try {
            $statement = database()->prepare('SELECT file_path FROM news_clippings WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $clippingId]);
            $clipping = $statement->fetch();
            $filePath = $clipping
                ? app_config()['app']['storage_path'] . DIRECTORY_SEPARATOR . 'news' . DIRECTORY_SEPARATOR . basename($clipping['file_path'])
                : '';
            if (!$clipping || !is_file($filePath)) {
                http_response_code(404);
                exit('File clipping tidak ditemukan.');
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($filePath);
            $allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];
            if (!in_array($mime, $allowedMime, true)) {
                http_response_code(415);
                exit('Format file tidak didukung.');
            }
            header('Content-Type: ' . $mime);
            header('Content-Disposition: inline; filename="news-clipping-' . $clippingId . '"');
            header('X-Content-Type-Options: nosniff');
            header('Content-Length: ' . (string) filesize($filePath));
            readfile($filePath);
        } catch (PDOException $exception) {
            error_log('News file query failed: ' . $exception->getMessage());
            http_response_code(500);
            exit('File clipping belum dapat diakses.');
        }
        exit;
    }

    if ($path === '/admin/news' && $method === 'GET') {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pustakawan') {
            flash('error', 'Hanya pustakawan yang dapat mengelola news clippings.');
            legacy_redirect('/dashboard');
        }
        $clippings = [];
        $flash = consume_flash();
        try {
            $clippings = database()->query(
                'SELECT id, title, source_media, publish_date, created_at FROM news_clippings ORDER BY publish_date DESC, id DESC'
            )->fetchAll();
        } catch (PDOException $exception) {
            error_log('Admin news listing failed: ' . $exception->getMessage());
            $flash = ['type' => 'error', 'message' => 'News clippings belum dapat dimuat.'];
        }
        $pageTitle = 'Kelola news clippings';
        require dirname(__DIR__) . '/views/admin/news.php';
        exit;
    }

    if ($path === '/admin/news' && $method === 'POST') {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pustakawan') {
            flash('error', 'Hanya pustakawan yang dapat mengelola news clippings.');
            legacy_redirect('/dashboard');
        }
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Sesi formulir kedaluwarsa. Silakan coba lagi.');
            legacy_redirect('/admin/news');
        }
        $title = trim((string) ($_POST['title'] ?? ''));
        $sourceMedia = trim((string) ($_POST['source_media'] ?? ''));
        $publishDate = trim((string) ($_POST['publish_date'] ?? ''));
        $file = $_FILES['clipping_file'] ?? null;
        try {
            if ($title === '' || mb_strlen($title) > 255 || $sourceMedia === '' || mb_strlen($sourceMedia) > 100) {
                throw new RuntimeException('Judul dan media sumber wajib diisi.');
            }
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $publishDate);
            if (!$date || $date->format('Y-m-d') !== $publishDate) {
                throw new RuntimeException('Tanggal terbit tidak valid.');
            }
            if (!is_array($file)
                || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))
                || ($file['size'] ?? 0) <= 0
                || ($file['size'] ?? 0) > 20 * 1024 * 1024) {
                throw new RuntimeException('File clipping wajib diunggah dan maksimal 20 MB.');
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
                throw new RuntimeException('File clipping harus PDF, JPG, atau PNG.');
            }
            $directory = app_config()['app']['storage_path'] . DIRECTORY_SEPARATOR . 'news';
            if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
                throw new RuntimeException('Penyimpanan clipping tidak dapat dibuat.');
            }
            $extension = match ($mime) {
                'application/pdf' => 'pdf',
                'image/jpeg' => 'jpg',
                default => 'png',
            };
            $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
            if (!move_uploaded_file($file['tmp_name'], $directory . DIRECTORY_SEPARATOR . $storedName)) {
                throw new RuntimeException('File clipping gagal disimpan.');
            }
            $statement = database()->prepare(
                'INSERT INTO news_clippings (title, source_media, publish_date, file_path, uploaded_by)
                 VALUES (:title, :source_media, :publish_date, :file_path, :uploaded_by)'
            );
            $statement->execute([
                'title' => $title,
                'source_media' => $sourceMedia,
                'publish_date' => $publishDate,
                'file_path' => $storedName,
                'uploaded_by' => $_SESSION['user']['id'],
            ]);
            flash('success', 'News clipping berhasil diunggah.');
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        } catch (PDOException $exception) {
            error_log('News upload failed: ' . $exception->getMessage());
            flash('error', 'News clipping gagal disimpan.');
        }
        legacy_redirect('/admin/news');
    }

    if ($path === '/e-resources' && $method === 'GET') {
        $resources = [];
        $flash = consume_flash();
        try {
            $resources = database()->query(
                'SELECT id, title, description FROM e_resources ORDER BY title ASC'
            )->fetchAll();
        } catch (PDOException $exception) {
            error_log('E-resource listing failed: ' . $exception->getMessage());
            $flash = ['type' => 'error', 'message' => 'E-Resources belum dapat dimuat.'];
        }
        $pageTitle = 'E-Resources';
        require dirname(__DIR__) . '/views/e-resources.php';
        exit;
    }

    if ($path === '/e-resources/go' && $method === 'GET') {
        if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['anggota', 'pustakawan'], true)) {
            flash('error', 'E-Resources hanya dapat diakses anggota internal.');
            legacy_redirect('/login');
        }
        $resourceId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if ($resourceId === false || $resourceId < 1) {
            http_response_code(404);
            exit('E-Resource tidak ditemukan.');
        }
        try {
            $statement = database()->prepare('SELECT url_link FROM e_resources WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $resourceId]);
            $resource = $statement->fetch();
            if (!$resource || !filter_var($resource['url_link'], FILTER_VALIDATE_URL) || !in_array(parse_url($resource['url_link'], PHP_URL_SCHEME), ['http', 'https'], true)) {
                http_response_code(404);
                exit('E-Resource tidak valid.');
            }
            $activity = database()->prepare(
                "INSERT INTO e_resource_activities (e_resource_id, user_id, activity_type)
                 VALUES (:resource_id, :user_id, 'click')"
            );
            $activity->execute(['resource_id' => $resourceId, 'user_id' => $_SESSION['user']['id']]);
            header('Location: ' . $resource['url_link'], true, 302);
        } catch (PDOException $exception) {
            error_log('E-resource redirect failed: ' . $exception->getMessage());
            http_response_code(500);
            exit('E-Resource belum dapat diakses.');
        }
        exit;
    }

    if ($path === '/admin/e-resources' && $method === 'GET') {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pustakawan') {
            flash('error', 'Hanya pustakawan yang dapat mengelola E-Resources.');
            legacy_redirect('/dashboard');
        }
        $resources = [];
        $flash = consume_flash();
        try {
            $resources = database()->query(
                "SELECT er.id, er.title, er.url_link, er.description,
                        COUNT(era.id) AS click_count
                 FROM e_resources er
                 LEFT JOIN e_resource_activities era ON era.e_resource_id = er.id AND era.activity_type = 'click'
                 GROUP BY er.id ORDER BY er.title ASC"
            )->fetchAll();
        } catch (PDOException $exception) {
            error_log('Admin E-resource listing failed: ' . $exception->getMessage());
            $flash = ['type' => 'error', 'message' => 'E-Resources belum dapat dimuat.'];
        }
        $pageTitle = 'Kelola E-Resources';
        require dirname(__DIR__) . '/views/admin/e-resources.php';
        exit;
    }

    if (($path === '/admin/e-resources' && $method === 'POST')
        || $path === '/admin/maintenance/overdue'
        || ($path === '/admin/reports' && $method === 'GET')) {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pustakawan') {
            flash('error', 'Hanya pustakawan yang dapat mengelola E-Resources.');
            legacy_redirect('/dashboard');
        }
        if ($path === '/admin/e-resources' && !verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Sesi formulir kedaluwarsa. Silakan coba lagi.');
            legacy_redirect('/admin/e-resources');
        }

        if ($path === '/admin/maintenance/overdue' && $method === 'POST') {
            if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pustakawan') {
                flash('error', 'Hanya pustakawan yang dapat menjalankan pemeliharaan sirkulasi.');
                legacy_redirect('/dashboard');
            }
            if (!verify_csrf($_POST['csrf_token'] ?? null)) {
                flash('error', 'Sesi formulir kedaluwarsa. Silakan coba lagi.');
                legacy_redirect('/dashboard');
            }
            try {
                $statement = database()->prepare(
                    "UPDATE circulations
                     SET status = 'overdue'
                     WHERE status = 'active' AND due_date < CURDATE()"
                );
                $statement->execute();
                flash('success', (string) $statement->rowCount() . ' peminjaman ditandai sebagai terlambat.');
            } catch (PDOException $exception) {
                error_log('Overdue maintenance failed: ' . $exception->getMessage());
                flash('error', 'Pemeliharaan status overdue gagal dijalankan.');
            }
            legacy_redirect('/dashboard');
        }

        if ($path === '/admin/reports' && $method === 'GET') {
            if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pustakawan') {
                flash('error', 'Hanya pustakawan yang dapat melihat laporan.');
                legacy_redirect('/dashboard');
            }
            $report = (string) ($_GET['report'] ?? 'circulation');
            $allowedReports = ['circulation', 'fines', 'reservations', 'catalog'];
            if (!in_array($report, $allowedReports, true)) {
                $report = 'circulation';
            }
            $rows = [];
            $flash = consume_flash();
            try {
                $pdo = database();
                if ($report === 'circulation') {
                    $rows = $pdo->query(
                        "SELECT c.id, u.email, cb.title, bi.barcode, c.borrow_date, c.due_date,
                                c.return_date, c.status, c.fine_amount
                         FROM circulations c
                         INNER JOIN users u ON u.id = c.user_id
                         INNER JOIN book_items bi ON bi.id = c.book_item_id
                         INNER JOIN catalog_books cb ON cb.id = bi.catalog_id
                         ORDER BY c.id DESC LIMIT 500"
                    )->fetchAll();
                } elseif ($report === 'fines') {
                    $rows = $pdo->query(
                        "SELECT u.email, cb.title, c.due_date, c.return_date, c.status, c.fine_amount
                         FROM circulations c
                         INNER JOIN users u ON u.id = c.user_id
                         INNER JOIN book_items bi ON bi.id = c.book_item_id
                         INNER JOIN catalog_books cb ON cb.id = bi.catalog_id
                         WHERE c.fine_amount > 0
                         ORDER BY c.fine_amount DESC, c.id DESC LIMIT 500"
                    )->fetchAll();
                } elseif ($report === 'reservations') {
                    $rows = $pdo->query(
                        "SELECT u.email, cb.title, r.reservation_code, r.status, r.created_at
                         FROM reservations r
                         INNER JOIN users u ON u.id = r.user_id
                         INNER JOIN catalog_books cb ON cb.id = r.catalog_id
                         ORDER BY r.created_at DESC LIMIT 500"
                    )->fetchAll();
                } else {
                    $rows = $pdo->query(
                        "SELECT cb.title, cb.type, cb.author, cb.publisher, cb.publication_year,
                                COUNT(bi.id) AS item_count,
                                COUNT(CASE WHEN bi.status = 'available' THEN 1 END) AS available_stock,
                                COUNT(CASE WHEN bi.status = 'borrowed' THEN 1 END) AS borrowed_stock
                         FROM catalog_books cb
                         LEFT JOIN book_items bi ON bi.catalog_id = cb.id
                         GROUP BY cb.id ORDER BY cb.title ASC LIMIT 500"
                    )->fetchAll();
                }
            } catch (PDOException $exception) {
                error_log('Report query failed: ' . $exception->getMessage());
                $flash = ['type' => 'error', 'message' => 'Laporan belum dapat dimuat.'];
            }
            if (($_GET['format'] ?? '') === 'csv') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="digital-library-' . $report . '.csv"');
                $output = fopen('php://output', 'wb');
                if ($rows !== []) {
                    fputcsv($output, array_keys($rows[0]));
                    foreach ($rows as $row) {
                        fputcsv($output, $row);
                    }
                }
                fclose($output);
                exit;
            }
            $pageTitle = 'Laporan perpustakaan';
            require dirname(__DIR__) . '/views/admin/reports.php';
            exit;
        }
        $action = (string) ($_POST['action'] ?? '');
        $resourceId = filter_var($_POST['resource_id'] ?? null, FILTER_VALIDATE_INT);
        try {
            if ($action === 'delete') {
                if ($resourceId === false || $resourceId < 1) {
                    throw new RuntimeException('E-Resource tidak valid.');
                }
                database()->prepare('DELETE FROM e_resources WHERE id = :id')->execute(['id' => $resourceId]);
                flash('success', 'E-Resource berhasil dihapus.');
            } elseif ($action === 'save') {
                $title = trim((string) ($_POST['title'] ?? ''));
                $url = trim((string) ($_POST['url_link'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));
                if ($title === '' || !filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
                    throw new RuntimeException('Judul dan URL HTTP/HTTPS yang valid wajib diisi.');
                }
                if ($resourceId !== false && $resourceId > 0) {
                    database()->prepare('UPDATE e_resources SET title=:title, url_link=:url_link, description=:description WHERE id=:id')
                        ->execute(['title' => $title, 'url_link' => $url, 'description' => $description ?: null, 'id' => $resourceId]);
                    flash('success', 'E-Resource berhasil diperbarui.');
                } else {
                    database()->prepare('INSERT INTO e_resources (title, url_link, description) VALUES (:title, :url_link, :description)')
                        ->execute(['title' => $title, 'url_link' => $url, 'description' => $description ?: null]);
                    flash('success', 'E-Resource berhasil ditambahkan.');
                }
            } else {
                throw new RuntimeException('Tindakan E-Resource tidak dikenal.');
            }
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        } catch (PDOException $exception) {
            error_log('E-resource write failed: ' . $exception->getMessage());
            flash('error', 'Perubahan E-Resource gagal disimpan.');
        }
        legacy_redirect('/admin/e-resources');
    }
if (($path === '/reserve' || $path === '/waitlist' || $path === '/circulation') && $method === 'POST') {
    if ($path !== '/circulation' && !isset($_SESSION['user'])) {
        flash('error', 'Silakan masuk sebagai anggota untuk menggunakan reservasi.');
        legacy_redirect('/login');
    }
    if ($path !== '/circulation' && $_SESSION['user']['role'] !== 'anggota') {
        flash('error', 'Reservasi buku fisik hanya tersedia untuk anggota internal.');
        legacy_redirect('/catalog');
    }
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('error', 'Sesi formulir kedaluwarsa. Silakan coba lagi.');
        legacy_redirect($path === '/circulation' ? '/dashboard' : '/catalog');
    }

    $catalogId = filter_var($_POST['catalog_id'] ?? null, FILTER_VALIDATE_INT);
    $returnQuery = trim((string) ($_POST['return_query'] ?? ''));
    $returnType = trim((string) ($_POST['return_type'] ?? ''));
    $catalogUrl = '/catalog';
    if ($returnQuery !== '' || $returnType !== '') {
        $catalogUrl .= '?' . http_build_query(array_filter([
            'q' => $returnQuery,
            'type' => $returnType,
        ], static fn (string $value): bool => $value !== ''));
    }

    if ($path === '/circulation' && $method === 'POST') {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pustakawan') {
            flash('error', 'Hanya pustakawan yang dapat mengelola sirkulasi.');
            legacy_redirect('/dashboard');
        }
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Sesi formulir kedaluwarsa. Silakan coba lagi.');
            legacy_redirect('/dashboard');
        }

        $action = (string) ($_POST['action'] ?? '');
        $memberIdentifier = trim((string) ($_POST['member_identifier'] ?? ''));
        $barcode = trim((string) ($_POST['barcode'] ?? ''));
        $pdo = null;

        if (!in_array($action, ['borrow', 'return', 'extend'], true) || $memberIdentifier === '' || $barcode === '') {
            flash('error', 'Pilih tindakan dan isi identitas anggota serta barcode buku.');
            legacy_redirect('/dashboard');
        }

        try {
            $pdo = database();
            $pdo->beginTransaction();

            $userStatement = $pdo->prepare(
                "SELECT id, email, nip, role, status
                 FROM users
                 WHERE (email = :email_identifier OR nip = :nip_identifier)
                   AND role IN ('anggota', 'eksternal')
                 LIMIT 1
                 FOR UPDATE"
            );
            $userStatement->execute([
                'email_identifier' => $memberIdentifier,
                'nip_identifier' => $memberIdentifier,
            ]);
            $member = $userStatement->fetch();
            if (!$member || $member['status'] !== 'active') {
                throw new RuntimeException('Anggota tidak ditemukan atau tidak aktif.');
            }
            if ($member['role'] !== 'anggota') {
                throw new RuntimeException('Sirkulasi fisik hanya tersedia untuk anggota internal.');
            }

            $itemStatement = $pdo->prepare(
                "SELECT bi.id, bi.catalog_id, bi.status, cb.title
                 FROM book_items bi
                 INNER JOIN catalog_books cb ON cb.id = bi.catalog_id
                 WHERE bi.barcode = :barcode
                 LIMIT 1
                 FOR UPDATE"
            );
            $itemStatement->execute(['barcode' => $barcode]);
            $item = $itemStatement->fetch();
            if (!$item) {
                throw new RuntimeException('Barcode buku tidak ditemukan.');
            }

            $circulationConfig = app_config()['circulation'];

            if ($action === 'borrow') {
                if (!in_array($item['status'], ['available', 'reserved'], true)) {
                    throw new RuntimeException('Buku tidak tersedia untuk dipinjam.');
                }

                $reservationStatement = $pdo->prepare(
                    "SELECT id
                     FROM reservations
                     WHERE user_id = :user_id AND catalog_id = :catalog_id
                       AND status = 'pending_pickup'
                     ORDER BY created_at ASC
                     LIMIT 1
                     FOR UPDATE"
                );
                $reservationStatement->execute([
                    'user_id' => $member['id'],
                    'catalog_id' => $item['catalog_id'],
                ]);
                $reservation = $reservationStatement->fetch();
                if ($item['status'] === 'reserved' && !$reservation) {
                    throw new RuntimeException('Buku sedang dicadangkan untuk anggota lain.');
                }

                $borrowStatement = $pdo->prepare(
                    "INSERT INTO circulations (user_id, book_item_id, borrow_date, due_date, status)
                     VALUES (:user_id, :book_item_id, CURDATE(), DATE_ADD(CURDATE(), INTERVAL :loan_days DAY), 'active')"
                );
                $borrowStatement->execute([
                    'user_id' => $member['id'],
                    'book_item_id' => $item['id'],
                    'loan_days' => (int) $circulationConfig['loan_days'],
                ]);
                $pdo->prepare("UPDATE book_items SET status = 'borrowed' WHERE id = :item_id")
                    ->execute(['item_id' => $item['id']]);
                if ($reservation) {
                    $pdo->prepare("UPDATE reservations SET status = 'completed' WHERE id = :reservation_id")
                        ->execute(['reservation_id' => $reservation['id']]);
                }
                $message = 'Peminjaman berhasil dicatat untuk ' . $item['title'] . '.';
            } else {
                $activeStatement = $pdo->prepare(
                    "SELECT c.id, c.user_id, c.due_date, c.status, c.fine_amount, c.book_item_id
                     FROM circulations c
                     WHERE c.book_item_id = :book_item_id AND c.status IN ('active', 'overdue')
                     LIMIT 1
                     FOR UPDATE"
                );
                $activeStatement->execute(['book_item_id' => $item['id']]);
                $circulation = $activeStatement->fetch();
                if (!$circulation || (int) $circulation['user_id'] !== (int) $member['id']) {
                    throw new RuntimeException('Peminjaman aktif untuk anggota dan barcode tersebut tidak ditemukan.');
                }

                if ($action === 'extend') {
                    if ($circulation['status'] !== 'active') {
                        throw new RuntimeException('Peminjaman yang sudah terlambat tidak dapat diperpanjang.');
                    }
                    $extendStatement = $pdo->prepare(
                        "UPDATE circulations
                         SET due_date = DATE_ADD(due_date, INTERVAL :extension_days DAY)
                         WHERE id = :circulation_id AND status = 'active'"
                    );
                    $extendStatement->execute([
                        'extension_days' => (int) $circulationConfig['extension_days'],
                        'circulation_id' => $circulation['id'],
                    ]);
                    $message = 'Masa peminjaman berhasil diperpanjang.';
                } else {
                    $today = new DateTimeImmutable('today');
                    $dueDate = new DateTimeImmutable($circulation['due_date']);
                    $lateDays = max(0, (int) $dueDate->diff($today)->format('%r%a'));
                    $fine = $lateDays * (int) $circulationConfig['daily_fine'];

                    $returnStatement = $pdo->prepare(
                        "UPDATE circulations
                         SET return_date = CURDATE(), status = 'returned', fine_amount = :fine_amount
                         WHERE id = :circulation_id"
                    );
                    $returnStatement->execute([
                        'fine_amount' => $fine,
                        'circulation_id' => $circulation['id'],
                    ]);
                    $pdo->prepare("UPDATE book_items SET status = 'available' WHERE id = :item_id")
                        ->execute(['item_id' => $item['id']]);

                    $waitingStatement = $pdo->prepare(
                        "SELECT id, user_id
                         FROM reservations
                         WHERE catalog_id = :catalog_id AND status = 'waiting_list'
                         ORDER BY created_at ASC
                         LIMIT 1
                         FOR UPDATE"
                    );
                    $waitingStatement->execute(['catalog_id' => $item['catalog_id']]);
                    $waiting = $waitingStatement->fetch();
                    if ($waiting) {
                        $reservationCode = 'RSV-' . strtoupper(bin2hex(random_bytes(4)));
                        $pdo->prepare(
                            "UPDATE reservations
                             SET status = 'pending_pickup', reservation_code = :reservation_code
                             WHERE id = :reservation_id AND status = 'waiting_list'"
                        )->execute([
                            'reservation_code' => $reservationCode,
                            'reservation_id' => $waiting['id'],
                        ]);
                        $pdo->prepare("UPDATE book_items SET status = 'reserved' WHERE id = :item_id")
                            ->execute(['item_id' => $item['id']]);
                        $message = 'Pengembalian berhasil. Denda: Rp ' . number_format($fine, 0, ',', '.') . '. Buku otomatis dicadangkan untuk antrean berikutnya dengan kode ' . $reservationCode . '.';
                    } else {
                        $message = 'Pengembalian berhasil. Denda: Rp ' . number_format($fine, 0, ',', '.') . '.';
                    }
                }
            }

            $pdo->commit();
            flash('success', $message);
        } catch (RuntimeException $exception) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception->getMessage());
        } catch (PDOException $exception) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Circulation failed: ' . $exception->getMessage());
            flash('error', 'Transaksi sirkulasi gagal diproses.');
        }

        legacy_redirect('/dashboard');
    }

    if ($catalogId === false || $catalogId < 1) {
        flash('error', 'Koleksi yang dipilih tidak valid.');
        legacy_redirect($catalogUrl);
    }

    $pdo = null;
    try {
        $pdo = database();
        $pdo->beginTransaction();

        $catalogStatement = $pdo->prepare(
            "SELECT id, type FROM catalog_books WHERE id = :catalog_id FOR UPDATE"
        );
        $catalogStatement->execute(['catalog_id' => $catalogId]);
        $catalog = $catalogStatement->fetch();
        if (!$catalog || $catalog['type'] !== 'physical') {
            throw new RuntimeException('Koleksi ini tidak dapat dipesan secara fisik.');
        }

        $limitStatement = $pdo->prepare(
            "SELECT COUNT(*) FROM reservations
             WHERE user_id = :user_id AND status IN ('pending_pickup', 'waiting_list')"
        );
        $limitStatement->execute(['user_id' => $_SESSION['user']['id']]);
        if ((int) $limitStatement->fetchColumn() >= 3) {
            throw new RuntimeException('Batas reservasi aktif Anda sudah mencapai 3 buku.');
        }

        $duplicateStatement = $pdo->prepare(
            "SELECT id FROM reservations
             WHERE user_id = :user_id AND catalog_id = :catalog_id
               AND status IN ('pending_pickup', 'waiting_list')
             LIMIT 1"
        );
        $duplicateStatement->execute([
            'user_id' => $_SESSION['user']['id'],
            'catalog_id' => $catalogId,
        ]);
        if ($duplicateStatement->fetch()) {
            throw new RuntimeException('Anda sudah memiliki reservasi atau antrean untuk buku ini.');
        }

        $itemStatement = $pdo->prepare(
            "SELECT id FROM book_items
             WHERE catalog_id = :catalog_id AND status = 'available'
             ORDER BY id ASC LIMIT 1 FOR UPDATE"
        );
        $itemStatement->execute(['catalog_id' => $catalogId]);
        $item = $itemStatement->fetch();

        $isWaitingList = $path === '/waitlist';
        if ($isWaitingList && $item) {
            throw new RuntimeException('Buku sudah tersedia. Silakan gunakan tombol reservasi.');
        }
        if (!$isWaitingList && !$item) {
            throw new RuntimeException('Buku sedang tidak tersedia. Gunakan tombol daftar tunggu.');
        }

        $status = $isWaitingList ? 'waiting_list' : 'pending_pickup';
        $code = $isWaitingList ? null : 'RSV-' . strtoupper(bin2hex(random_bytes(4)));
        $reservationStatement = $pdo->prepare(
            'INSERT INTO reservations (user_id, catalog_id, reservation_code, status)
             VALUES (:user_id, :catalog_id, :reservation_code, :status)'
        );
        $reservationStatement->execute([
            'user_id' => $_SESSION['user']['id'],
            'catalog_id' => $catalogId,
            'reservation_code' => $code,
            'status' => $status,
        ]);

        if ($item) {
            $updateItem = $pdo->prepare(
                "UPDATE book_items SET status = 'reserved' WHERE id = :item_id AND status = 'available'"
            );
            $updateItem->execute(['item_id' => $item['id']]);
            if ($updateItem->rowCount() !== 1) {
                throw new RuntimeException('Salinan buku baru saja berubah status. Silakan coba lagi.');
            }
        }

        $pdo->commit();
        flash(
            'success',
            $isWaitingList
                ? 'Anda berhasil masuk daftar tunggu buku ini.'
                : 'Buku berhasil direservasi. Kode reservasi: ' . $code
        );
    } catch (RuntimeException $exception) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $exception->getMessage());
    } catch (PDOException $exception) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Reservation failed: ' . $exception->getMessage());
        flash('error', 'Reservasi gagal diproses. Silakan coba lagi.');
    }

    legacy_redirect($catalogUrl);
}

http_response_code(404);
$pageTitle = 'Halaman tidak ditemukan';
render_view('errors/404', compact('pageTitle'));

