-- Personal Todo List Schema
-- Date: 2026-03-30

CREATE TABLE IF NOT EXISTS todos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    due_date DATE NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'medium',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_todos_restaurant_user (restaurant_id, user_id),
    INDEX idx_todos_status (status),
    INDEX idx_todos_due_date (due_date),
    CONSTRAINT fk_todos_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_todos_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sidebar row for nav-todos lives in docs/sql/nav_permissions.sql
