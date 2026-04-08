<?php
// Simple URL router

class Router {
    private static array $routes = [];

    public static function get(string $pattern, callable $handler): void {
        self::$routes[] = ['GET', $pattern, $handler];
    }

    public static function post(string $pattern, callable $handler): void {
        self::$routes[] = ['POST', $pattern, $handler];
    }

    public static function any(string $pattern, callable $handler): void {
        self::$routes[] = ['ANY', $pattern, $handler];
    }

    public static function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Strip base path
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($base && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        if ($uri === '' || $uri === false) $uri = '/';

        foreach (self::$routes as [$routeMethod, $pattern, $handler]) {
            if ($routeMethod !== 'ANY' && $routeMethod !== $method) continue;

            // Convert :param patterns to regex
            $regex = preg_replace('/\/:([a-zA-Z_]+)/', '/(?P<$1>[^/]+)', $pattern);
            $regex = '#^' . $regex . '$#';

            if (preg_match($regex, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler($params);
                return;
            }
        }

        // 404
        http_response_code(404);
        include APP_PATH . '/templates/error.php';
    }
}
