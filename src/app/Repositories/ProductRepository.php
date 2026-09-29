<?php

namespace App\Repositories;

use Core\Query;
use PDO;

/**
 * Repository for reading and writing product records.
 */
class ProductRepository 
{

  private Query $products;


  /** 
   * @var \PDOStatement Statement to select product ids by SKU 
  */
  private \PDOStatement $selectBySku;

  /** 
    * @var \PDOStatement Statement to insert new product rows 
  */
  private \PDOStatement $insertProduct;

  /**
   *  @var \PDOStatement Statement to update product rows by SKU 
  */
  private \PDOStatement $updateProduct;

  /**
   * Prepare reusable statements for product queries.
   */
  public function __construct()
  {
    $this->products = Query::table('products');
    $pdo = $this->products->pdo();

    $this->selectBySku = $pdo->prepare(
      'SELECT id, sku FROM products WHERE sku = :sku'
    );
    $this->insertProduct = $pdo->prepare(
      'INSERT INTO products (
        import_id, sku, name, price, stock, created_at, updated_at
       ) VALUES (
        :import_id, :sku, :name, :price, :stock, :created_at, :updated_at
       )'
    );
    $this->updateProduct = $pdo->prepare(
      'UPDATE products
       SET name = :name,
          price = :price,
          stock = :stock,
          updated_at = :updated_at
       WHERE sku = :sku'
    );
  }

  /**
   * Retrieve a product row by SKU.
   *
   * @param string $sku Product SKU to search for.
   * @return array|null Associative row with id and sku or null when not found.
   */
  public function findBySku(string $sku): ?array
  {
    $this->selectBySku->execute([':sku' => $sku]);
    $row = $this->selectBySku->fetch(PDO::FETCH_ASSOC);
    $this->selectBySku->closeCursor();

    return $row ?: null;
  }

  /**
   * Insert a new product record.
   *
   * @param int    $importId Import job identifier.
   * @param string $sku      Product SKU.
   * @param string $name     Product name.
   * @param float  $price    Product price.
   * @param int    $stock    Inventory count.
   * @param string $now      Timestamp for created_at/updated_at columns.
   */
  public function insertProduct(
    int $importId,
    string $sku,
    string $name,
    float $price,
    int $stock,
    string $now
  ): void {
      $this->insertProduct->execute([
        ':import_id'  => $importId,
        ':sku'        => $sku,
        ':name'       => $name,
        ':price'      => $price,
        ':stock'      => $stock,
        ':created_at' => $now,
        ':updated_at' => $now,
      ]);
  }

  /**
   * Update product fields matched by SKU.
   *
   * @param string $sku   Product SKU to update.
   * @param string $name  New product name.
   * @param float  $price New product price.
   * @param int    $stock New inventory count.
   * @param string $now   Timestamp for updated_at column.
   */
  public function updateBySku(
    string $sku,
    string $name,
    float $price,
    int $stock,
    string $now
  ): void {
      $this->updateProduct->execute([
        ':sku'        => $sku,
        ':name'       => $name,
        ':price'      => $price,
        ':stock'      => $stock,
        ':updated_at' => $now,
      ]);
  }

  /**
   * Retrieve a paginated list of products.
   *
   * @param int $limit  Number of records to retrieve.
   * @param int $offset Offset to start retrieving records from.
   * @return array List of associative product rows.
   */
  public function paginate(int $limit, int $offset): array
  {
    return $this->products->get($limit, $offset, 'id', 'ASC');
  }
}
