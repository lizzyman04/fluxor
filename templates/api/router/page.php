<?php
/**
 * API Homepage - GET /
 */

use MVCCore\Flow;
use MVCCore\Core\Response;

Flow::GET()->do(function ($req) {
    return Response::success([
        'message' => 'Welcome to MVCCore API 🚀',
        'version' => '1.0.0',
        'timestamp' => time(),
        'endpoints' => [
            'GET /api/v1/users' => 'List users',
            'POST /api/v1/users' => 'Create user',
            'GET /api/v1/posts' => 'List posts',
            'POST /api/v1/posts' => 'Create post'
        ],
        'documentation' => '/docs'
    ]);
});

return Flow::execute($req);