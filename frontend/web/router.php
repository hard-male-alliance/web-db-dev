<?php
/** Development/demo HTTP router; never exposes the repository or private uploads. */
declare(strict_types=1);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_string($path) && preg_match('~^/assets/[a-zA-Z0-9_.-]+$~D', $path)) {
    $file = __DIR__ . $path;
    if (is_file($file)) {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $mime = ['css' => 'text/css; charset=UTF-8', 'js' => 'application/javascript; charset=UTF-8', 'svg' => 'image/svg+xml'];
        if (isset($mime[$extension])) {
            header('Content-Type: ' . $mime[$extension]);
            readfile($file);
            return true;
        }
    }
    http_response_code(404);
    return true;
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
if ($path === '/health') {
    $_GET['r'] = 'site/health';
}
if ($path === '/admin' || str_starts_with((string) $path, '/admin/')) {
    require dirname(__DIR__, 2) . '/backend/web/index.php';
} else {
    require __DIR__ . '/index.php';
}
return true;
