<?php

declare(strict_types=1);

namespace Clover\View;

final class View
{
    protected string $viewsPath = '';
    protected string $publicPath = '';
    protected array $data = [];
    protected array $extensions = ['.html', '.php', '.blade.php', '.twig'];
    protected array $routes = [];
    protected bool $useDefaultFallback = true;
    protected string $defaultFile = '/index';

    public function __construct(array $data = []) {
        $this->data = $data;
    }

    public function filePath(string $path): self {
        $this->viewsPath = rtrim($path, '/\\');
        return $this;
    }

    public function public(string $path): self {
        $this->publicPath = rtrim($path, '/\\');
        return $this;
    }

    public function addRoute(string $pattern, array $routeData = []): self {
        $this->routes[$pattern] = $routeData;
        return $this;
    }

    public function defaultFallback(bool $enable = true, string $file = '/index'): self {
        $this->useDefaultFallback = $enable;
        $this->defaultFile = $file;
        return $this;
    }

    public function serve(): void {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Serve public files
        if ($this->publicPath) {
            $publicFile = realpath($this->publicPath . $uri);
            if ($publicFile && is_file($publicFile)) {
                $this->serveFile($publicFile);
                return;
            }
        }

        // Match dynamic route parameters
        $params = $this->matchRoute($uri);
        $data = array_merge($this->data, $params);

        // Serve direct view file
        foreach ($this->extensions as $ext) {
            $viewFile = rtrim($this->viewsPath, '/\\') . $uri . $ext;
            if (file_exists($viewFile)) {
                $this->renderView($viewFile, $data);
                return;
            }
        }

        // Check registered dynamic routes
        foreach ($this->routes as $pattern => $routeData) {
            $regex = preg_replace('/\{([\w]+)\}/', '(?P<$1>[^/]+)', $pattern);
            $regex = '#^' . $regex . '$#';
            if (preg_match($regex, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $data = array_merge($this->data, $params, $routeData);

                foreach ($this->extensions as $ext) {
                    $viewFile = rtrim($this->viewsPath, '/\\') . $pattern . $ext;
                    if (file_exists($viewFile)) {
                        $this->renderView($viewFile, $data);
                        return;
                    }
                }
            }
        }

        // Default fallback
        if ($this->useDefaultFallback) {
            foreach ($this->extensions as $ext) {
                $fallbackFile = rtrim($this->viewsPath, '/\\') . $this->defaultFile . $ext;
                if (file_exists($fallbackFile)) {
                    $this->renderView($fallbackFile, $this->data);
                    return;
                }
            }
        }

        //http_response_code(404);
        echo "Page not found: $uri";
    }

    protected function serveFile(string $file): void {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mimeTypes = [
            'css'=>'text/css','js'=>'application/javascript',
            'png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg',
            'gif'=>'image/gif','svg'=>'image/svg+xml',
            'woff'=>'font/woff','woff2'=>'font/woff2',
            'ttf'=>'font/ttf','otf'=>'font/otf',
            'eot'=>'application/vnd.ms-fontobject','ico'=>'image/x-icon',
            'html'=>'text/html','htm'=>'text/html'
        ];
        header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=86400');
        readfile($file);
        exit;
    }

    protected function renderView(string $file, array $data): void {
        extract($data);
        if (str_ends_with($file, '.twig') && class_exists('\Twig\Environment')) {
            $loader = new \Twig\Loader\FilesystemLoader($this->viewsPath);
            $twig = new \Twig\Environment($loader);
            $template = str_replace([$this->viewsPath, '.twig'], '', $file);
            echo $twig->render($template, $data);
            exit;
        }
        if (str_ends_with($file, '.blade.php') && class_exists('\Jenssegers\Blade\Blade')) {
            $blade = new \Jenssegers\Blade\Blade($this->viewsPath, __DIR__.'/cache');
            echo $blade->render(str_replace([$this->viewsPath, '.blade.php'], '', $file), $data);
            exit;
        }
        include $file;
        exit;
    }

    protected function matchRoute(string $uri): array {
        foreach ($this->routes as $pattern => $routeData) {
            $regex = preg_replace('/\{([\w]+)\}/', '(?P<$1>[^/]+)', $pattern);
            $regex = '#^' . $regex . '$#';
            if (preg_match($regex, $uri, $matches)) {
                return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            }
        }
        return [];
    }
}
