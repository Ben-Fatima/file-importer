<?php

namespace App\Controllers;

use App\Services\Upload;
use Core\Controller;

/**
 * Handles chunked upload HTTP endpoints.
 */
class UploadController extends Controller
{

  /**
   * Upload service handling chunk persistence.
   *
   * @var Upload
   */
  private Upload $upload;

  /**
   * Instantiate the upload controller with a service instance.
   */
  public function __construct() {
    $this->upload = Upload::getInstance();
  }
   
  /**
   * Render the upload form HTML.
   *
   * @return void
   */
  public function form(): void
  {
    $this->renderHtml('upload/form');
  }

  /**
   * Initialize a new upload session and return its identifier.
   *
   * Expects JSON body with `filename` and `fileSizeBytes`.
   *
   * @return void
   */
  public function init(): void
  {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $filename      = $input['filename']      ?? '';
    $fileSizeBytes = (int)($input['fileSizeBytes'] ?? 0);
    if ($filename === '' || $fileSizeBytes <= 0) {
      http_response_code(400);
      echo json_encode(['error' => 'Invalid filename or file size']);
      return;
    }
    $uploadId = $this->upload->init();
    echo json_encode(['upload_id' => $uploadId]);
  }

  /**
   * Append a chunk to an in-progress upload.
   *
   * Expects query params `upload_id` and `offset`; raw body contains the chunk.
   *
   * @return void
   */
  public function chunk(): void
  {
    header('Content-Type: application/json');
    $uploadId = $_GET['upload_id'] ?? '';
    $offset   = isset($_GET['offset']) ? (int)$_GET['offset'] : -1;
    $chunk    = file_get_contents('php://input');
    if ($uploadId === '' || $offset < 0) {
      http_response_code(400);
      echo json_encode(['error' => 'Invalid upload ID or offset']);
      return;
    }
    if ($chunk === '' || $chunk === false) {
      http_response_code(400);
      echo json_encode(['error' => 'Empty chunk']);
      return;
    }
    $bytesWritten = $this->upload->addChunk($uploadId, $offset, $chunk);
    echo json_encode([
      'bytes_written' => $bytesWritten, 
      'offset'        => $offset,
      'upload_id'     => $uploadId,
    ]);
  }

  /**
   * Finalize an upload and persist metadata.
   *
   * Expects query params `upload_id` and `original_name`.
   *
   * @return void
   */
  public function complete(): void
  {
    header('Content-Type: application/json');
    $uploadId     = $_GET['upload_id']     ?? '';
    $originalName = $_GET['original_name'] ?? '';
    if ($uploadId === '' || $originalName === '') {
      http_response_code(400);
      echo json_encode(['error' => 'Missing upload_id or original_name']);
      return;
    }

    $res = $this->upload->complete($uploadId, $originalName);
    echo json_encode([
      'import_id' => $res['import_id'],
      'file_size' => $res['final_size'],
      'message'   => 'Upload complete',
    ]);
  }
}
