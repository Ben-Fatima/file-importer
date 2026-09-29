<?php

namespace App\Repositories;

use Core\Query;
/**
 * Repository for interacting with the `imports` table.
 */
class ImportRepository
{

  private Query $imports;
  /**
   * Initialise repository with the imports table.
   */
  public function __construct()
  {
    $this->imports = Query::table('imports');
  }

  /**
   * Fetch an import record by its primary key.
   *
   * @param int $id
   * @return array|null
   */
  public function findById(int $id): ?array
  {
    return $this->imports->where('id', '=', $id)->first();
  }

  /**
   * Update the progress status for an import.
   *
   * @param int $id
   * @param string $status
   * @return void
   */
  public function setStatus(int $id, string $status): void
  {
    $this->imports->where('id', '=', $id)->update([
      'progress_status' => $status,
      'updated_at'      => date('c'),
    ]);
  }

  /**
   * Update processing progress metrics for an import.
   *
   * @param int $id
   * @param int $bytesProcessed
   * @param int $processedRows
   * @param bool $finished
   * @return void
   */
  public function updateProgress(
      int $id,
      int $bytesProcessed,
      int $processedRows,
      bool $finished
  ): void {
    $status = $finished ? 'finished' : 'processing';

    $this->imports->where('id', '=', $id)->update([
      'bytes_processed' => $bytesProcessed,
      'processed_rows'  => $processedRows,
      'progress_status' => $status,
      'updated_at'      => date('c'),
    ]);
  }

  /**
   * Create a new import record with initial metadata.
   *
   * @param string $fileName
   * @param string $filePath
   * @param int $fileSizeBytes
   * @return int Inserted record ID.
   */
  public function createImport(
    string $fileName,
    string $filePath,
    int $fileSizeBytes
  ): int {
    $now = date('c');
    return $this->imports->insert([
      'file_name'       => $fileName,
      'file_path'       => $filePath,
      'progress_status' => 'pending',
      'file_size_bytes' => $fileSizeBytes,
      'bytes_processed' => 0,
      'total_rows'      => null,
      'processed_rows'  => 0,
      'created_at'      => $now,
      'updated_at'      => $now,
    ]);
  }
}
