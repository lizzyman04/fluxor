<?php
/**
 * Global 404 Not Found handler
 */

use MVCCore\Core\Response;

return function ($req) {
    $requestedPath = $req->param('requested_path', $req->path);
    
    if ($req->wantsJson()) {
        return Response::error(
            "Endpoint '{$requestedPath}' not found", 
            404,
            ['available_endpoints' => ['/', '/api/hello']]
        );
    }
    
    // Simple HTML response for browsers
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>404 - Not Found</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 100px auto; text-align: center; }
        h1 { color: #333; }
        .path { background: #f5f5f5; padding: 10px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>404 - Page Not Found</h1>
    <div class="path">Requested path: <strong>{$requestedPath}</strong></div>
    <p>The page you are looking for does not exist.</p>
    <a href="/">Go to Homepage</a>
</body>
</html>
HTML;

    return Response::html($html, 404);
};