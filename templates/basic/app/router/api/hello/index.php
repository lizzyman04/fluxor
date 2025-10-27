<?php
/**
 * API Hello endpoint - GET /api/hello
 */

use MVCCore\Flow;
use MVCCore\Core\Response;

Flow::GET()->do(function ($req) {
    $name = $req->query['name'] ?? 'World';
    
    return Response::success([
        'message' => "Hello, {$name}! 👋",
        'timestamp' => time(),
        'request' => [
            'method' => $req->method,
            'query' => $req->query,
            'user_agent' => $req->userAgent
        ]
    ]);
});

Flow::POST()->do(function ($req) {
    $name = $req->input('name', 'World');
    
    return Response::success([
        'message' => "Hello, {$name}! (POST)",
        'received_data' => $req->all(),
        'timestamp' => time()
    ], 201);
});

return Flow::execute($req);