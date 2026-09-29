<?php

namespace App\Repositories;

use Core\Query;

/**
 * Repository for persisting import row errors to the database.
 */
class ErrorRepository
{

  private Query $errors;
  /**
   * Prepared statement used to insert error rows.
   *
   * @var \PDOStatement
   */
  private \PDOStatement $insertError;


  /**
   * Initialize repository with prepared insert statement.
   */
  public function __construct()
  {
    $this->errors = Query::table('errors');
    $pdo = $this->errors->pdo();

    $this->insertError = $pdo->prepare(
      'INSERT INTO errors (
        import_id, row_number, sku, message, raw_row, created_at
       ) VALUES (
        :import_id, :row_number, :sku, :message, :raw_row, :created_at
       )'
    );
  }

  /**
   * Record an error for a specific imported row.
   *
   * @param int $importId ID of the import batch.
   * @param int $rowNumber Row number within the import file.
   * @param string|null $sku SKU associated with the row, if present.
   * @param array $errors List of validation or processing errors.
   * @param array $rawRow Original row data being imported.
   * @param string $now Timestamp string for creation time.
   *
   * @return void
   */
  public function logRowError(
    int $importId,
    int $rowNumber,
    ?string $sku,
    array $errors,
    array $rawRow,
    string $now
    ): void {
      $this->insertError->execute([
        ':import_id'  => $importId,
        ':row_number' => $rowNumber,
        ':sku'        => $sku,
        ':message'    => implode('; ', $errors),
        ':raw_row'    => json_encode($rawRow),
        ':created_at' => $now,
      ]);
    }

  public function listByImportId(int $importId): array
  {
    return Query::table('errors')
      ->where('import_id', '=', $importId)
      ->get();
  }
}
