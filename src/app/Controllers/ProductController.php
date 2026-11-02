<?php

namespace App\Controllers;

use App\Repositories\ProductRepository;
use Core\Controller;

/**
 * Handles product listing pages.
 */
class ProductController extends Controller
{

  /**
   * Repository used to fetch product records.
   *
   * @var ProductRepository
   */
  private ProductRepository $products;

  /**
   * Set up the controller with a product repository instance.
   */
  public function __construct()
  {
    $this->products = new ProductRepository();
  }

  /**
   * Display a paginated list of products.
   *
   * @return void
   */
  public function index(): void 
  {
    $perPage = 50;
    $afterId = max(0, (int)($_GET['after'] ?? 0));
    $data = $this->products->paginateAfter($perPage, $afterId);
    $nextCursor = !empty($data) ? (int)end($data)['id'] : 0;
    $hasNext = count($data) === $perPage;

    $this->render('products/index', [
      'products'   => $data,
      'afterId'    => $afterId,
      'perPage'    => $perPage,
      'nextCursor' => $nextCursor,
      'hasNext'    => $hasNext,
    ]);
  }
}
