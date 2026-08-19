<?php
namespace App\Core;

class Router {
    private array $routes = [];
    private $notFoundHandler = null;

    public function get(string $path, callable $handler): self {
        $this->routes['GET'][$path] = $handler;
        return $this;
    }

    public function post(string $path, callable $handler): self {
        $this->routes['POST'][$path] = $handler;
        return $this;
    }

    public function any(string $path, callable $handler): self {
        $this->routes['GET'][$path] = $handler;
        $this->routes['POST'][$path] = $handler;
        return $this;
    }

    public function notFound(callable $handler): self {
        $this->notFoundHandler = $handler;
        return $this;
    }

    public function dispatch(string $method, string $uri): void {
        // Strip query string
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';

        // Check exact match
        if (isset($this->routes[$method][$path])) {
            call_user_func($this->routes[$method][$path]);
            return;
        }

        // Check with trailing slash
        if (isset($this->routes[$method][$path . '/'])) {
            call_user_func($this->routes[$method][$path . '/']);
            return;
        }

        // Check without trailing slash
        $trimmed = rtrim($path, '/');
        if ($trimmed !== $path && isset($this->routes[$method][$trimmed])) {
            call_user_func($this->routes[$method][$trimmed]);
            return;
        }

        // Check any-method routes
        foreach (['GET', 'POST'] as $m) {
            if (isset($this->routes[$m][$path])) {
                call_user_func($this->routes[$m][$path]);
                return;
            }
        }

        // 404
        if ($this->notFoundHandler !== null) {
            call_user_func($this->notFoundHandler);
        } else {
            http_response_code(404);
            echo '<h1>404 Not Found</h1>';
        }
    }
}
