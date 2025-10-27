<?php
/**
 * Homepage route - GET /
 */

use MVCCore\Flow;
use MVCCore\Core\Response;

Flow::GET()->do(function ($req) {
    return Response::success([
        'message' => 'Welcome to MVCCore! 🚀',
        'template' => 'basic',
        'version' => '1.0.0',
        'timestamp' => time(),
        'endpoints' => [
            '/api/hello' => 'GET - Simple API endpoint',
            '/api/hello?name=YourName' => 'GET - Personalized greeting'
        ]
    ]);
});

return Flow::execute($req);