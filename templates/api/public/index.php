<?php
/**
 * MVCCore - API Template
 * RESTful API application
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Initialize application
$app = new MVCCore\Core\App(dirname(__DIR__));

// API-specific configuration
$app->setConfig([
    'router_path' => dirname(__DIR__) . '/app/router',
    'base_url' => $_ENV['APP_URL'] ?? 'http://localhost:8000'
]);

// Add CORS middleware for API
$app->getRouter()->addMiddleware('cors', function ($req) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
    
    if ($req->method === 'OPTIONS') {
        exit(0);
    }
});

// Run the application
$app->run();