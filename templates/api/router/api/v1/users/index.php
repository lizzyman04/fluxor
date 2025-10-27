<?php
/**
 * Users API - /api/v1/users
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
    // Simulated users data
    $users = [
        ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
        ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com']
    ];
    
    return Response::success($users, 'Users retrieved successfully');
});

Flow::POST()->do(function ($req) {
    $userData = $req->json ?? $req->body;
    
    // Validation
    if (empty($userData['name']) || empty($userData['email'])) {
        return Response::error('Name and email are required', 422);
    }
    
    // Simulate user creation
    $user = [
        'id' => rand(1000, 9999),
        'name' => $userData['name'],
        'email' => $userData['email'],
        'created_at' => date('c')
    ];
    
    return Response::success($user, 'User created successfully', 201);
});

return Flow::execute($req);