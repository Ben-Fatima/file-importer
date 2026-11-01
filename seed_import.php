<?php

/**
 * Registers a CSV already in storage/ as a pending import,
 * so a large file can be imported without going through the upload UI.
 *
 * Usage: php seed_import.php [file-name-in-storage]
 */

require __DIR__ . '/vendor/autoload.php';

use App\Repositories\ImportRepository;

$fileName = $argv[1] ?? 'big_products.csv';
$path = __DIR__ . '/storage/' . $fileName;

if (!file_exists($path)) {
    fwrite(STDERR, "File not found: {$path} — run generate_big_csv.php first.\n");
    exit(1);
}

$repo = new ImportRepository();
$id = $repo->createImport($fileName, $fileName, filesize($path));

echo "Import #{$id} created for {$fileName} (" . filesize($path) . " bytes)\n";
echo "Trigger it with: POST /import?id={$id}\n";
