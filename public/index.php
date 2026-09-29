<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\UploadController;
use App\Controllers\ImportController;
use App\Controllers\ProductController;
use Core\Router;

$router = new Router();

$router->redirect('/', '/upload');

$router->get('/upload',          [UploadController::class, 'form']);
$router->post('/upload/init',    [UploadController::class, 'init']);
$router->post('/upload/chunk',   [UploadController::class, 'chunk']);
$router->post('/upload/complete',[UploadController::class, 'complete']);

$router->post('/import',         [ImportController::class, 'runChunk']);
$router->get('/import/status',   [ImportController::class, 'status']);
$router->get('/import/errors-report', [ImportController::class, 'errorsReport']);

$router->get('/products', [ProductController::class, 'index']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);