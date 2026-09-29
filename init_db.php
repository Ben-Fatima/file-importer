<?php

$storageDir = __DIR__ . '/storage';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0777, true);
}

$dbPath = $storageDir . '/file_importer.sqlite';

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS imports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_name TEXT NOT NULL,
    file_path TEXT NOT NULL,
    progress_status TEXT NOT NULL CHECK (progress_status IN ('pending','processing','finished','failed')) DEFAULT 'pending',
    file_size_bytes INTEGER,
    bytes_processed INTEGER NOT NULL DEFAULT 0,
    total_rows INTEGER,
    processed_rows INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    import_id INTEGER NOT NULL,
    sku TEXT NOT NULL,
    name TEXT NOT NULL,
    price REAL NOT NULL,
    stock INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (import_id) REFERENCES imports(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_products_sku ON products(sku);

CREATE TABLE IF NOT EXISTS errors (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  import_id   INTEGER NOT NULL,
  row_number  INTEGER NOT NULL,
  sku         TEXT,
  message     TEXT NOT NULL,
  raw_row     TEXT,
  created_at  TEXT NOT NULL,
  FOREIGN KEY (import_id) REFERENCES imports(id)
);

CREATE INDEX IF NOT EXISTS idx_errors_import_id ON errors(import_id);

SQL;

$pdo->exec($sql);

echo "Database and the tables ready at {$dbPath}\n";