<?php
/**
 * Probe the HTTP server as well as its database connection.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$context = stream_context_create(['http' => ['timeout' => 3]]);
$body = @file_get_contents('http://127.0.0.1:8080/', false, $context);
if ($body === false || !str_contains($body, 'Local infrastructure is ready')) {
    fwrite(STDERR, "HTTP/database readiness failed.\n");
    exit(1);
}
