<?php

namespace App\Core;

abstract class Controller
{
    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
        $viewsPath = $appConfig['views_path'];

        // Rend le contenu de la vue dans un buffer
        extract($data, EXTR_SKIP);
        ob_start();
        $viewFile = $viewsPath . '/' . str_replace('.', '/', $view) . '.php';
        if (!file_exists($viewFile)) {
            throw new \RuntimeException("Vue introuvable : $viewFile");
        }
        require $viewFile;
        $content = ob_get_clean();

        // Injecte dans le layout
        $layoutFile = $viewsPath . '/layouts/' . $layout . '.php';
        if (!file_exists($layoutFile)) {
            throw new \RuntimeException("Layout introuvable : $layoutFile");
        }
        require $layoutFile;
    }

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    protected function flash(string $type, string $message): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash'][$type][] = $message;
    }

    protected function getFlashes(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $flashes = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flashes;
    }
}
