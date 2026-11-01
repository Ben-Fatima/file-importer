<?php

namespace App\Services;

use App\Database;
use App\Repositories\ProductRepository;
use App\Repositories\ErrorRepository;
use App\Repositories\ImportRepository;
use PDO;

/**
 * Handles chunked CSV imports, persisting products and tracking progress.
 */
class Import {

  /**
   * @var self|null
   */
  private static ?self $instance = null;

  /**
   * Absolute base path for storage directory.
   *
   * @var string
   */
  private string $path;

  /**
   * Repository for product persistence.
   *
   * @var ProductRepository
   */
  private ProductRepository $products;

  /**
   * Repository for row-level error logging.
   *
   * @var ErrorRepository
   */
  private ErrorRepository $errors;

  /**
   * Repository for import metadata.
   *
   * @var ImportRepository
   */
  private ImportRepository $imports;

  /**
   * Shared PDO connection.
   *
   * @var PDO
   */
  private PDO $db;

  /**
   * Set up dependencies and storage path.
   */
  private function __construct() {
    $this->db       = Database::getConnection();
    $this->imports  = new ImportRepository();
    $this->products = new ProductRepository();
    $this->errors   = new ErrorRepository();
    $this->path     = dirname(__DIR__, 3) . '/storage';
  }

  public static function getInstance(): self
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  /**
   * Process a chunk of rows for the given import.
   *
   * @param int $importId ID of the import job.
   * @param int $maxRows  Maximum rows to process in this run.
   * @return array Summary of chunk progress and errors.
   *
   * @throws \Exception When import id is missing or file cannot be opened.
   */
  public function runChunk(int $importId, int $maxRows = 1000) : array {

    $import = $this->imports->findById($importId);

    if (!$import) {
      throw new \Exception("Import with ID {$importId} not found.");
    }

    $this->ensureCanRun($import);
    $filePath = $this->buildAbsolutePath($import);
    $handle = $this->openFileAtProgress($filePath, (int)$import['bytes_processed']);
    
    if (!$handle) {
      throw new \Exception("Failed to open file at path {$filePath}.");
    }

    $created = 0;
    $updated = 0;
    $failed  = 0;
    $rowsThisChunk = 0;
    $errors = [];

    $bytesProcessed     = (int)$import['bytes_processed'];
    $processedRowsTotal = (int)$import['processed_rows'];

    $transactionStarted = false;

    try {
      if (!$this->db->inTransaction()) {
        $this->db->beginTransaction();
        $transactionStarted = true;
      }

      while ($rowsThisChunk < $maxRows && !feof($handle)) {
        $row = fgetcsv($handle);

        if ($row === false || $row === []) {
          break; 
        }

        $rowResult = $this->processRow($row, $importId,$processedRowsTotal + 1);

        $status = is_array($rowResult) ? $rowResult['status'] : $rowResult;

        switch ($status) {
          case 'created':
            $created++;
            break;
        case 'updated':
          $updated++;
          break;
        case 'failed':
          $failed++;
          $errors[] = $rowResult; 
          break;
        }

        $bytesProcessed = ftell($handle);
        $processedRowsTotal++;
        $rowsThisChunk++;
      }

      $finished = feof($handle);
      fclose($handle);
      $this->updateImportProgress(
        $importId,
        $bytesProcessed,
        $processedRowsTotal,
        $finished        
      );

      if ($transactionStarted && $this->db->inTransaction()) {
        $this->db->commit();
      }

      return [
          'import_id'       => $importId,
          'created'         => $created,
          'updated'         => $updated,
          'failed'          => $failed,
          'bytes_processed' => $bytesProcessed,
          'processed_rows'  => $processedRowsTotal,
          'done'            => $finished,
          'errors'          => $errors
      ];
    } catch (\Throwable $e) {

      if ($transactionStarted && $this->db->inTransaction()) {
        $this->db->rollBack();
      }

      if (is_resource($handle)) {
        fclose($handle);
      }
      throw $e;
    }
  }


  /**
   * Ensure the import is in a runnable state.
   * Transition pending to processing.
   *
   * @param array $import Import record.
   *
   * @throws \Exception If the import is already finished or failed.
   */
  private function ensureCanRun(array $import): void
  {
    if ($import['progress_status'] === 'finished' || $import['progress_status'] === 'failed') {
      throw new \Exception("Import with ID {$import['id']} cannot be processed in status '{$import['progress_status']}'.");
    } 
    elseif ($import['progress_status'] === 'pending') {
      $this->setImportStatus($import['id'], 'processing');
    }
  }

  /**
   * Update the import status.
   *
   * @param int    $importId Import ID.
   * @param string $status   New status value.
   */
  private function setImportStatus(int $importId, string $status): void
  {
    $this->imports->setStatus($importId, $status);
  }

  /**
   * Build the absolute filesystem path for an import file.
   *
   * @param array $import Import record.
   * @return string Absolute path to the file.
   */
  private function buildAbsolutePath(array $import): string
  {
    return $this->path . '/' . $import['file_path'];
  }

  /**
   * Open file handle positioned at the right place.
   * Handles skipping the header if bytes_processed == 0.
   *
   * @param string $path CSV file path.
   * @param int    $bytesProcessed Previously processed byte offset.
   * @return resource|false File handle or false on failure.
   *
   * @throws \Exception When headers are invalid.
   */
  private function openFileAtProgress(string $path, int $bytesProcessed): mixed
  {
    $handle = fopen($path, 'r');

    if ($handle === false) {
      return false;  
    }

    if ($bytesProcessed > 0) {

      if (fseek($handle, $bytesProcessed) !== 0) {
        fclose($handle);
        return false;
      }

    } else {
      $headers = fgetcsv($handle);

      if ($headers === false || !$this->validateHeaders($headers)) {
        fclose($handle);
        throw new \Exception('Invalid file format');
      }
    }

  return $handle;
  }

  /**
   * Process a single CSV row: validate, insert or update.
   * Return one of: 'created', 'updated', 'failed'.
   *
   * @param array $row       CSV row data.
   * @param int   $importId  Import ID.
   * @param int   $rowNumber 1-based row number in file (excluding header).
   * @return array|string Status info or failure detail.
   */
  private function processRow(array $row, int $importId, int $rowNumber): array|string
  {
    $sku   = trim($row[0] ?? '');
    $name  = trim($row[1] ?? '');
    $price = trim($row[2] ?? '');
    $stock = trim($row[3] ?? '0');

    $errors = $this->validateRow($sku, $name, $price, $stock);
    $now    = date('c');

    if (!empty($errors)) {
      $this->errors->logRowError(
          $importId,
          $rowNumber,
          $sku !== '' ? $sku : null,
          $errors,
          $row,
          $now
        );
      
      return [
        'status'     => 'failed',
        'errors'     => $errors,
        'row_number' => $rowNumber,
        'sku'        => $sku,
        'raw_row'    => json_encode($row),
      ];
    }

    $product = $this->products->findBySku($sku);

      if ($product) {
        $this->products->updateBySku(
          $sku,
          $name,
          (float) $price,
          (int) $stock,
          $now
        );
        return 'updated';
      }
      $this->products->insertProduct(
        $importId,
        $sku,
        $name,
        (float) $price,
        (int) $stock,
        $now
      );

      return 'created';
  }

private function updateImportProgress(
  int $importId,
  int $bytesProcessed,
  int $processedRows,
  bool $finished
): void {
  $this->imports->updateProgress($importId, $bytesProcessed, $processedRows, $finished);
}
  
  /**
   * Retrieve current status for an import.
   *
   * @param int $importId Import ID.
   * @return array|null Status payload or null when not found.
   */
  public function status(int $importId): ?array
  {
    $import = $this->imports->findById($importId);
    if (!$import) {
      return null;
    }
    $fileSize = (int)($import['file_size_bytes'] ?? 0);
    $bytes = (int)($import['bytes_processed'] ?? 0);
    $percentage = 0;
    if ($fileSize > 0) {
      $percentage = ($bytes / $fileSize) * 100;
    }
    if ($percentage > 100) {
      $percentage = 100;
    }
  
    return [
        'import_id'        => $importId,
        'file_name'      => $import['file_name'],
        'progress_status'  => $import['progress_status'],
        'processed_rows'   => $import['processed_rows'],
        'file_size_bytes'  => $fileSize,
        'bytes_processed'  => $bytes,
        'percentage'       => round($percentage, 2),
        'created_at'      => $import['created_at'],
        'updated_at'      => $import['updated_at']
    ];
  }

  /**
   * Validate a CSV row's fields.
   *
   * @param mixed $sku   SKU value.
   * @param mixed $name  Name value.
   * @param mixed $price Price value.
   * @param mixed $stock Stock value.
   * @return array Validation error messages.
   */
  private function validateRow($sku, $name, $price, $stock) : array {
    $errors = [];
    if($sku === '') {
      $errors[] = "SKU is required.";   
    }
    if($name === '') {
      $errors[] = "Name is required.";   
    }
    if(!is_numeric($price)) {
      $errors[] = "Price must be a valid number.";   
    }
    if(!is_numeric($stock)) {
      $errors[] = "Stock must be a valid integer.";   
    }
    return $errors;
  }

  /**
   * Build CSV error report for a given import.
   *
   * @param int $importId Import ID.
   * @return string|null CSV content or null if import missing.
   */
  public function getErrorReport(int $importId): ?string
  {
    $import = $this->imports->findById($importId);
    if (!$import) {
      return null;
    }
    $errors = $this->errors->listByImportId($importId);
    return $this->buildErrorsCsv($errors);
  }

  /**
   * Generate CSV from error records.
   *
   * @param array $errors Error rows.
   * @return string CSV content.
   */
  private function buildErrorsCsv(array $errors): string
  {
    $columns = ['row_number', 'sku', 'message', 'raw_row', 'created_at'];
    $handle = fopen('php://temp', 'r+');
    fputcsv($handle, $columns);

    foreach ($errors as $error) {
      fputcsv($handle, [
        $error['row_number'] ?? '',
        $error['sku'] ?? '',
        $error['message'] ?? '',
        $error['raw_row'] ?? '',
        $error['created_at'] ?? '',
      ]);
    }

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    return $csv === false ? '' : $csv;
  }

  /**
   * Validate CSV header ordering and names.
   *
   * @param array $headers Header row.
   * @return bool True when headers match expected values.
   */
  private function validateHeaders(array $headers): bool
  {
    if (count($headers) < 4) {
      return false;
    }

    $normalized = array_map(
      fn($h) => strtolower(trim((string) $h)),
      $headers
    );

    $expected = ['sku', 'name', 'price', 'stock'];

    for ($i = 0; $i < count($expected); $i++) {
        if (($normalized[$i] ?? null) !== $expected[$i]) {
          return false;
        }
    }
    return true;
  }
}
