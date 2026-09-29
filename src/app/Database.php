<?php

namespace App;

use PDO;
class Database {

  /** @var PDO|null Shared PDO connection instance */
  private static ?PDO $pdo = null;

  /**
   * Retrieve a shared PDO connection to the SQLite database.
   *
   * @return PDO Active PDO connection
   */
  public static function getConnection(): PDO {
    if (self::$pdo === null) {
      $root = dirname(__DIR__, 2);             
      $dbPath = $root . '/storage/file_importer.sqlite';
      $dsn = 'sqlite:' . $dbPath;
      $pdo = new PDO($dsn);
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      self::$pdo = $pdo;
    }
    return self::$pdo;
  }
  
}
