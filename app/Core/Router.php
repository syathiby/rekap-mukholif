<?php
declare(strict_types=1);

namespace App\Core;

class Router {
    private array $routes = [];

    public function get(string $path, string $controller, string $method): void {
        $this->addRoute('GET', $path, $controller, $method);
    }

    public function post(string $path, string $controller, string $method): void {
        $this->addRoute('POST', $path, $controller, $method);
    }

    private function addRoute(string $httpMethod, string $path, string $controller, string $method): void {
        // Convert route params like {id} into regex (?<id>[a-zA-Z0-9_-]+)
        $routeRegex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[a-zA-Z0-9_-]+)', $path);
        $routeRegex = "#^" . $routeRegex . "$#";
        
        $this->routes[] = [
            'method'     => $httpMethod,
            'path'       => $path,
            'regex'      => $routeRegex,
            'controller' => $controller,
            'action'     => $method
        ];
    }

    public function dispatch(string $requestUri, string $requestMethod): void {
        // Remove base url path if exists
        $basePath = parse_url($_ENV['APP_URL'] ?? '', PHP_URL_PATH) ?? '';
        if ($basePath && strpos($requestUri, $basePath) === 0) {
            $requestUri = substr($requestUri, strlen($basePath));
        }

        // Remove query string
        $parsedUrl = parse_url($requestUri);
        $path = $parsedUrl['path'] ?? '/';
        
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        
        if (empty($path)) {
            $path = '/';
        }

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                if ($route['method'] !== $requestMethod) {
                    continue; // Path matches, but method doesn't. Keep searching.
                }

                $controllerClass = "\\App\\Controllers\\" . $route['controller'];
                if (class_exists($controllerClass)) {
                    $controllerInstance = new $controllerClass();
                    $action = $route['action'];
                    
                    if (method_exists($controllerInstance, $action)) {
                        // Extract named parameters
                        $params = [];
                        foreach ($matches as $key => $value) {
                            if (is_string($key)) {
                                $params[$key] = $value;
                            }
                        }
                        
                        call_user_func_array([$controllerInstance, $action], $params);
                        return;
                    }
                }
            }
        }

        // Check if path exists but wrong method
        $pathExists = false;
        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path)) {
                $pathExists = true;
                break;
            }
        }

        if ($pathExists) {
            http_response_code(405);
            echo "405 Method Not Allowed";
        } else {
            http_response_code(404);
            if (file_exists(VIEW_PATH . '/errors/404.php')) {
                require VIEW_PATH . '/errors/404.php';
            } else {
                echo "<h1>404 Not Found</h1><p>The page you requested could not be found.</p>";
            }
        }
    }
}



