<?php
/**
 * Global 500 Server Error handler
 */

use MVCCore\Core\Response;

return function ($req) {
    $error = $req->param('exception');
    $isDev = ($_ENV['APP_ENV'] ?? 'production') === 'development';
    
    if ($req->wantsJson()) {
        $response = [
            'error' => 'Internal Server Error',
            'message' => 'Something went wrong on our end'
        ];
        
        if ($isDev && $error) {
            $response['debug'] = [
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine()
            ];
        }
        
        return Response::json($response, 500);
    }
    
    // HTML response
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>500 - Server Error</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 100px auto; text-align: center; }
        h1 { color: #d32f2f; }
    </style>
</head>
<body>
    <h1>500 - Server Error</h1>
    <p>Something went wrong on our end. Please try again later.</p>
    <a href="/">Go to Homepage</a>
</body>
</html>
HTML;

    return Response::html($html, 500);
};