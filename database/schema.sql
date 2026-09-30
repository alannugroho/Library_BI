CREATE DATABASE IF NOT EXISTS digital_library_bi
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE digital_library_bi;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nip VARCHAR(50) UNIQUE NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('anggota', 'pustakawan', 'eksternal') NOT NULL,
    status ENUM('active', 'inactive', 'pending') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE email_verifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token CHAR(64) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_email_verifications_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_email_verifications_expiry (token, expires_at)
) ENGINE=InnoDB;

CREATE TABLE catalog_books (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NULL,
    publisher VARCHAR(255) NULL,
    publication_year SMALLINT UNSIGNED NULL,
    isbn VARCHAR(50) NULL,
    udc_classification VARCHAR(100) NULL,
    type ENUM('physical', 'digital') NOT NULL,
    digital_file_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_catalog_title (title),
    INDEX idx_catalog_type (type)
) ENGINE=InnoDB;

CREATE TABLE book_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    catalog_id INT UNSIGNED NOT NULL,
    barcode VARCHAR(100) UNIQUE NOT NULL,
    shelf_location VARCHAR(100) NULL,
    status ENUM('available', 'reserved', 'borrowed', 'lost', 'maintenance') NOT NULL DEFAULT 'available',
    CONSTRAINT fk_book_items_catalog
        FOREIGN KEY (catalog_id) REFERENCES catalog_books (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_book_items_availability (catalog_id, status)
) ENGINE=InnoDB;

CREATE TABLE reservations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    catalog_id INT UNSIGNED NOT NULL,
    reservation_code VARCHAR(50) UNIQUE NULL,
    status ENUM('pending_pickup', 'completed', 'cancelled', 'waiting_list') NOT NULL DEFAULT 'pending_pickup',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reservations_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_reservations_catalog
        FOREIGN KEY (catalog_id) REFERENCES catalog_books (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_reservations_queue (catalog_id, status, created_at)
) ENGINE=InnoDB;

CREATE TABLE circulations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    book_item_id INT UNSIGNED NOT NULL,
    borrow_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE NULL,
    status ENUM('active', 'returned', 'overdue') NOT NULL DEFAULT 'active',
    fine_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_circulations_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_circulations_book_item
        FOREIGN KEY (book_item_id) REFERENCES book_items (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_circulations_status (status),
    INDEX idx_circulations_item (book_item_id, status)
) ENGINE=InnoDB;

CREATE TABLE book_proposals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NULL,
    status ENUM('submitted', 'reviewed', 'approved', 'rejected') NOT NULL DEFAULT 'submitted',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_book_proposals_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE news_clippings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    source_media VARCHAR(100) NULL,
    publish_date DATE NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_news_clippings_user
        FOREIGN KEY (uploaded_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_news_publish_date (publish_date)
) ENGINE=InnoDB;

CREATE TABLE e_resources (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    url_link TEXT NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE e_resource_activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    e_resource_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    activity_type ENUM('first_login', 'click') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_e_resource_activities_resource
        FOREIGN KEY (e_resource_id) REFERENCES e_resources (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_e_resource_activities_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_e_resource_activities_lookup (e_resource_id, activity_type, created_at)
) ENGINE=InnoDB;
