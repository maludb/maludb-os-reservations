-- Good News Table
-- Date: 2026-04-09
-- Stores positive news articles fetched daily via web search
-- Restaurants can display these to patrons during registration

CREATE TABLE IF NOT EXISTS good_news (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    summary TEXT NULL,
    source_name VARCHAR(255) NULL,
    source_url VARCHAR(500) NULL,
    image_url VARCHAR(500) NULL,
    category VARCHAR(100) NULL,
    published_date DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_good_news_restaurant (restaurant_id),
    INDEX idx_good_news_active (restaurant_id, is_active),
    INDEX idx_good_news_fetched (fetched_at),
    CONSTRAINT fk_good_news_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
