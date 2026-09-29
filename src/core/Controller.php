<?php

namespace Core;

abstract class Controller
{
  protected function render(string $view, array $data = []): void
  {
    extract($data, EXTR_SKIP);
    $viewPath = $this->viewPath($view, 'php');
    ob_start();
    require $viewPath;
    echo ob_get_clean();
  }

  protected function renderHtml(string $view): void
  {
    $viewPath = $this->viewPath($view, 'html');
    echo file_get_contents($viewPath);
  }

  protected function viewPath(string $view, string $ext = 'php'): string
  {
    $view = ltrim($view, '/');
    $path = __DIR__ . '/../app/views/' . $view . '.' . $ext;
    if (!is_file($path)) {
      throw new \RuntimeException("View not found: {$path}");
    }
    return $path;
  }
}
