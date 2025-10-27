<?php
/**
 * Posts API - /api/v1/posts
 */

use MVCCore\Flow;
use MVCCore\Core\Response;

// API key middleware
Flow::use(function ($req) {
    $apiKey = $req->bearerToken() ?? $req->headers['X-API-Key'] ?? null;

    if (!$apiKey || $apiKey !== ($_ENV['API_KEY'] ?? 'test-key')) {
        return Response::error('Unauthorized', 401);
    }
});

Flow::GET()->do(function ($req) {
    $page = max(1, intval($req->query['page'] ?? 1));
    $limit = min(50, max(1, intval($req->query['limit'] ?? 10)));

    // Simulated posts data with pagination
    $posts = array_map(function ($i) use ($page) {
        return [
            'id' => ($page - 1) * 10 + $i,
            'title' => "Post {$i}",
            'content' => "This is the content of post {$i}",
            'author' => 'Author ' . $i,
            'created_at' => date('c', time() - rand(0, 86400 * 30))
        ];
    }, range(1, $limit));

    return Response::success([
        'data' => $posts,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => 100, // simulated total
            'pages' => ceil(100 / $limit)
        ]
    ], 'Posts retrieved successfully');
});

Flow::POST()->do(function ($req) {
    $postData = $req->json ?? $req->body;

    // Validation
    if (empty($postData['title']) || empty($postData['content'])) {
        return Response::error('Title and content are required', 422);
    }

    // Simulate post creation
    $post = [
        'id' => rand(1000, 9999),
        'title' => $postData['title'],
        'content' => $postData['content'],
        'author_id' => 1, // simulated author
        'created_at' => date('c'),
        'slug' => strtolower(str_replace(' ', '-', $postData['title']))
    ];

    return Response::success($post, 'Post created successfully', 201);
});

return Flow::execute($req);