<?php

/**
 * Generates a large products CSV (~1.2M rows, ~50 MB) into storage/
 * so the importer can be exercised against a realistic file.
 */

$path = __DIR__ . '/storage/big_products.csv';

if (!is_dir(dirname($path))) {
    mkdir(dirname($path), 0777, true);
}

$handle = fopen($path, 'w');
fputcsv($handle, ['sku', 'name', 'price', 'stock']);

$rows = 1200000;
for ($i = 1; $i <= $rows; $i++) {
    fputcsv($handle, [
        'SKU' . $i,
        'Product ' . $i,
        number_format(mt_rand(100, 99999) / 100, 2, '.', ''),
        mt_rand(0, 200),
    ]);
}

fclose($handle);

echo "Wrote {$rows} rows to {$path} (" . round(filesize($path) / 1048576, 1) . " MB)\n";
