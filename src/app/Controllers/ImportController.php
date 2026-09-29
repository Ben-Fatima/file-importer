<?php

namespace App\Controllers;

use App\Services\Import;

/**
 * Handles import process endpoints 
 * including chunk execution, status, and error reports.
 */
class ImportController
{

  /**
   * Import service used to perform import operations.
   *
   * @var Import
   */
  private Import $import;

  /**
   * Set up the controller with a fresh import service instance.
   */
  public function __construct() {
    $this->import = Import::getInstance();
  }

  /**
   * Execute a chunk of an import identified by `id` query parameter and return JSON response.
   *
   * @return void
   */
  public function runChunk(): void {
    header('Content-Type: application/json');
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
      http_response_code(400);
      echo json_encode(['error' => 'Missing or invalid import id']);
      return;
    }
    try {
      $result  = $this->import->runChunk($id,1000);
      echo json_encode($result);
    } catch (\Throwable $e) {
      http_response_code(500);
      echo json_encode(['error' => $e->getMessage()]);
    }
  }

  /**
   * Return the status of an import identified by `id` query parameter in JSON format.
   *
   * @return void
   */
  public function status(): void {
    header('Content-Type: application/json');
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
      http_response_code(400);
      echo json_encode(['error' => 'Missing or invalid import id']);
      return;
    }
    $status  = $this->import->status($id);
    if ($status === null) {
      http_response_code(404);
      echo json_encode(['error' => 'Import not found']);
      return;
    }
    echo json_encode($status);
  }

  /**
   * Stream a CSV error report for an import identified by `import_id` query parameter.
   *
   * @return void
   */
  public function errorsReport():void {
    $import_id = isset($_GET['import_id']) ? (int)$_GET['import_id'] : 0;

    if ($import_id <= 0) {
      header('Content-Type: application/json');
      http_response_code(400);
      echo json_encode(['error' => 'Missing or invalid import id']);
      return;
    }

    $csv = $this->import->getErrorReport($import_id);
    if ($csv === null) {
      header('Content-Type: application/json');
      http_response_code(404);
      echo json_encode(['error' => 'Import error report not found']);
      return;
    }
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="import-' . $import_id . '-errors.csv"');
    echo $csv;
    return;
  }
}
