<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->routes[] = ['GET', $path, $handler];
    }

    public function post(string $path, array $handler): void
    {
        $this->routes[] = ['POST', $path, $handler];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Normaliser : enlever le base path si nécessaire
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($basePath !== '' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }
        $uri = '/' . ltrim($uri, '/');

        foreach ($this->routes as [$routeMethod, $routePath, $handler]) {
            if ($method !== $routeMethod && !($method === 'POST' && $routeMethod === 'GET')) {
                if ($method !== $routeMethod) {
                    continue;
                }
            }
            if ($method !== $routeMethod) {
                continue;
            }

            $pattern = $this->buildPattern($routePath);

            if (preg_match($pattern, $uri, $matches)) {
                // Paramètres nommés issus de la route
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                [$controllerClass, $action] = $handler;

                if (!class_exists($controllerClass)) {
                    $this->abort(500, "Controller introuvable : $controllerClass");
                    return;
                }

                $controller = new $controllerClass();

                if (!method_exists($controller, $action)) {
                    $this->abort(500, "Action introuvable : $action");
                    return;
                }

                $controller->$action($params);
                return;
            }
        }

        $this->abort(404, 'Page introuvable');
    }

    private function buildPattern(string $path): string
    {
        // Convertit /eset/licenses/{id} en regex avec groupes nommés
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function abort(int $code, string $message): void
    {
        http_response_code($code);
        echo "<h1>$code</h1><p>" . htmlspecialchars($message) . '</p>';
    }
}
