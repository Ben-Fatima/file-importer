<?php 
namespace Core;

/**
 * Minimal HTTP router that registers path/method handlers and dispatches requests.
 * Supports `GET` and `POST` mappings defined as [class, method] pairs or callables.
 */
class Router {

  /** 
   * @var array
   * Registry of routes keyed by HTTP method and path 
  */
  private array $routes = [];

  /**
   * Register a GET route.
   *
   * @param string $path URL path to match.
   * @param array $handler Controller class and method pair.
   */
  public function get(string $path, array $handler): void {
    $this->addRoute('GET', $path, $handler);
  }
  
  /**
   * Register a POST route.
   *
   * @param string $path URL path to match.
   * @param array $handler Controller class and method pair.
   */
  public function post(string $path, array $handler): void {
    $this->addRoute('POST', $path, $handler);
  }

  /**
   * Store a route handler for a given method and path.
   *
   * @param string $method HTTP method (GET|POST).
   * @param string $path URL path to match.
   * @param array $handler Handler as [class, method].
   */
  private function addRoute(string $method, string $path, array $handler): void {
    $this->routes[$method][$path] = $handler;
  }

  /**
   * Dispatch the request to the matched handler or emit 404 when missing.
   *
   * @param string $method HTTP method of the incoming request.
   * @param string $uri Full request URI.
   */
  public function dispatch(string $method, string $uri): void {

    $path = parse_url($uri, PHP_URL_PATH);

    if (!isset($this->routes[$method][$path])) {
      http_response_code(404);
      echo 'Not found';
      return;
    }

    $handler = $this->routes[$method][$path];

    if (is_array($handler) && count($handler) === 2) {
      [$class, $methodName] = $handler;
      $controller = new $class();
      $controller->$methodName();
    } elseif (is_callable($handler)) {
      $handler();
    } else {
      throw new \RuntimeException('Invalid route handler');
    }
  }

  /**
   * Redirect to a path with an optional status code.
   *
   * @param string $from Source path to match.
   * @param string $to Destination path to redirect to.
   * @param int $statusCode HTTP status code for redirect.
   */
  public function redirect(string $from, string $to, int $statusCode = 302): void
  {
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

    if ($from === '') $from = '/';
    if ($from[0] !== '/') $from = '/' . $from;
    if ($to !== '' && $to[0] !== '/') $to = '/' . $to;

    if ($from === $to) {
      return;
    }
  
    if ($currentPath !== $from) {
      return;
    }
  
    header('Location: ' . $to, true, $statusCode);
    exit;
  }
}
