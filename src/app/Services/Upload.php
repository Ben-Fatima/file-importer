<?php

namespace App\Services;

use App\Repositories\ImportRepository;

/**
 * Handle chunked uploads and persistence of imported CSV files.
 */
class Upload
{

  /**
   * @var self|null
   */
  private static ?self $instance = null;

  /**
   * @var string Storage directory for uploaded files.
   */
  private string $storageDir;

  /**
   * @var string Temporary directory for chunked uploads.
   */
  private string $tmpDir;

  /**
   * @var ImportRepository Repository used to persist import metadata.
   */
  private ImportRepository $imports;
   
  private function __construct()
  {
    $this->storageDir = dirname(__DIR__, 3) . '/storage/uploads';
    $this->tmpDir = dirname(__DIR__, 3) . '/storage/tmp';
    $this->imports = new ImportRepository();
  }

  public static function getInstance(): self
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  /**
   * Validate the uploaded file array.
   *
   * @param array $file Incoming `$_FILES` entry.
   * @return bool True when the upload is present and error-free.
   */
  public function validate(array $file): bool
  {
    return isset($file['error']) && $file['error'] === UPLOAD_ERR_OK;
  }


  /**
   * Initialize a chunked upload by creating an empty temp file.
   *
   * @return string Unique upload identifier.
   */
  public function init() : string {
    
    $uploadId = uniqid('upload_', true);

    $this->ensureDirExists($this->tmpDir);
    $tmpPath = $this->getTmpFilePath($uploadId);
    file_put_contents($tmpPath, '');

    return $uploadId;
  }

  /**
   * Append a chunk to the temp file at the provided offset.
   *
   * @param string $uploadId Identifier returned by init().
   * @param int $offset Byte offset within the temp file.
   * @param string $chunk Raw chunk contents.
   * @return int Number of bytes written.
   * @throws \Exception When the temp file cannot be found or opened.
   */
  public function addChunk(string $uploadId, int $offset, string $chunk) : int {
    
    $tempFile = $this->getTmpFilePath($uploadId);

    if(!file_exists($tempFile)) {
      throw new \Exception('Upload temp file not found');
    }

    $handle = fopen($tempFile, 'c+b');
    if(!$handle) {
      throw new \Exception('Failed to open upload temp file');
    }

    fseek($handle, $offset);
    $bytesWritten = fwrite($handle, $chunk);
    fclose($handle);

    return $bytesWritten;
  }

  /**
   * Complete the upload by moving the temp file to storage/uploads.
   *
   * @param string $uploadId Identifier returned by init().
   * @param string $originalName Original client file name.
   * @return array{import_id:int,final_size:int} Import record details.
   * @throws \Exception When the temp file is missing or cannot be moved.
   */
  public function complete(string $uploadId,string $originalName) : array {

    $tmpFile = $this->getTmpFilePath($uploadId);

    if(!file_exists($tmpFile)) {
      throw new \Exception('Upload temp file not found'); 
    }

    $storageDir = $this->storageDir;
    $this->ensureDirExists($storageDir);
    $uniqueName = uniqid('import_', true) . '.csv';
    $finalPath = $storageDir . '/' . $uniqueName;

    if(!rename($tmpFile, $finalPath)) {
      throw new \Exception('Failed to move uploaded file to final destination');
    }

    $finalSize = filesize($finalPath);
    $relativePath = 'uploads/' . $uniqueName;
    $importId = $this->imports->createImport($originalName, $relativePath, $finalSize);

    return ['import_id' => $importId, 'final_size' => $finalSize];
  }

  /**
   * Ensure a directory exists, creating it recursively when absent.
   *
   * @param string $path Directory path to verify.
   * @return void
   */
  private function ensureDirExists(string $path): void {
    if(!is_dir($path)) {
      mkdir($path, 0777, true);
    }
  }

  /**
   * Build the temp file path for a given upload ID.
   *
   * @param string $uploadId Identifier returned by init().
   * @return string Full path to the temp part file.
   */
  private function getTmpFilePath(string $uploadId): string
  {
    return $this->tmpDir . '/' . $uploadId . '.part';
  }
}
