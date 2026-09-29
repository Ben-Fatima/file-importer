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
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;
    $data = $this->products->paginate($perPage, $offset);
    $hasPrev = $page > 1;
    $hasNext = count($data) === $perPage;

    $this->render('products/index', [
      'products' => $data,
      'page'     => $page,
      'perPage'  => $perPage,
      'hasPrev'  => $hasPrev,
      'hasNext'  => $hasNext,
    ]);
  }
}
