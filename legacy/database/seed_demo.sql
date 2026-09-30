USE digital_library_bi;

-- Local preview accounts. Passwords are documented in the project handoff, not for production use.
INSERT INTO users (nip, email, password_hash, role, status)
VALUES
    ('198001010001', 'pustakawan.demo@library.test', '$2y$10$hKJ7/gol5.sNapXHGl7guuDOicBQYHEjv3.GspJJ19VTMiHylwnTa', 'pustakawan', 'active'),
    ('199001010002', 'anggota.demo@library.test', '$2y$10$mVLk07DBB35gHUlv0ezCNOl4dleGIIGaZHwX1M7a4P7bLOjXaqjdK', 'anggota', 'active'),
    (NULL, 'eksternal.demo@library.test', '$2y$10$uR4CkmxE.YYszprCP3ZKBOEKlTKd5wj5UdNMe9E2MfKodOpS91/zS', 'eksternal', 'active')
ON DUPLICATE KEY UPDATE
    nip = VALUES(nip),
    password_hash = VALUES(password_hash),
    role = VALUES(role),
    status = VALUES(status);

INSERT INTO catalog_books (title, author, publisher, publication_year, isbn, udc_classification, type)
SELECT 'Dasar-Dasar Manajemen Perpustakaan', 'Sutarno NS', 'Gramedia', 2022, '9786020000001', '025', 'physical'
WHERE NOT EXISTS (
    SELECT 1 FROM catalog_books WHERE isbn = '9786020000001'
);

INSERT INTO catalog_books (title, author, publisher, publication_year, isbn, udc_classification, type)
SELECT 'Pengantar Sistem Informasi', 'Kadir', 'Andi Publisher', 2021, '9786020000002', '004', 'physical'
WHERE NOT EXISTS (
    SELECT 1 FROM catalog_books WHERE isbn = '9786020000002'
);

INSERT INTO catalog_books (title, author, publisher, publication_year, isbn, udc_classification, type)
SELECT 'Literasi Digital untuk Pendidikan', 'Tim Literasi Nasional', 'Pusat Data dan Teknologi', 2023, '9786020000003', '020', 'digital'
WHERE NOT EXISTS (
    SELECT 1 FROM catalog_books WHERE isbn = '9786020000003'
);

INSERT INTO book_items (catalog_id, barcode, shelf_location, status)
SELECT cb.id, 'DEMO-BOOK-001', 'A-01', 'available'
FROM catalog_books cb
WHERE cb.isbn = '9786020000001'
  AND NOT EXISTS (SELECT 1 FROM book_items WHERE barcode = 'DEMO-BOOK-001');

INSERT INTO book_items (catalog_id, barcode, shelf_location, status)
SELECT cb.id, 'DEMO-BOOK-002', 'A-02', 'available'
FROM catalog_books cb
WHERE cb.isbn = '9786020000002'
  AND NOT EXISTS (SELECT 1 FROM book_items WHERE barcode = 'DEMO-BOOK-002');

INSERT INTO book_items (catalog_id, barcode, shelf_location, status)
SELECT cb.id, 'DEMO-BOOK-002B', 'A-02', 'available'
FROM catalog_books cb
WHERE cb.isbn = '9786020000002'
  AND NOT EXISTS (SELECT 1 FROM book_items WHERE barcode = 'DEMO-BOOK-002B');

INSERT INTO e_resources (title, url_link, description)
SELECT 'Directory of Open Access Journals', 'https://doaj.org/', 'Contoh sumber jurnal akses terbuka untuk demo.'
WHERE NOT EXISTS (
    SELECT 1 FROM e_resources WHERE url_link = 'https://doaj.org/'
);

INSERT INTO e_resources (title, url_link, description)
SELECT 'Perpustakaan Nasional Republik Indonesia', 'https://www.perpusnas.go.id/', 'Portal resmi Perpustakaan Nasional RI.'
WHERE NOT EXISTS (
    SELECT 1 FROM e_resources WHERE url_link = 'https://www.perpusnas.go.id/'
);
