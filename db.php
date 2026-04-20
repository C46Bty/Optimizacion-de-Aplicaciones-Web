<?php
function getDB() {
    $dbPath = __DIR__ . '/data/rss_reader.db';
    if (!is_dir(__DIR__ . '/data')) {
        mkdir(__DIR__ . '/data', 0777, true);
    }
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA journal_mode=WAL;");

    $db->exec("
        CREATE TABLE IF NOT EXISTS feeds (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            url TEXT NOT NULL UNIQUE,
            name TEXT,
            last_fetched DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS news (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            feed_id INTEGER,
            feed_name TEXT,
            title TEXT,
            url TEXT,
            description TEXT,
            pub_date DATETIME,
            categories TEXT,
            guid TEXT UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (feed_id) REFERENCES feeds(id)
        );

        CREATE INDEX IF NOT EXISTS idx_news_feed_id  ON news(feed_id);
        CREATE INDEX IF NOT EXISTS idx_news_pub_date ON news(pub_date DESC);
        CREATE INDEX IF NOT EXISTS idx_news_title    ON news(title);
        CREATE INDEX IF NOT EXISTS idx_news_guid     ON news(guid);
    ");

    // Agregar columna last_fetched si no existe (para bases de datos ya creadas)
    try {
        $db->exec("ALTER TABLE feeds ADD COLUMN last_fetched DATETIME DEFAULT NULL;");
    } catch (Exception $e) {
        // La columna ya existe, ignorar
    }

    return $db;
}
