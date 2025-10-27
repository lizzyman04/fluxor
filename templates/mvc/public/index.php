<?php
/**
 * MVCCore - MVC Template
 * Full-featured MVC application
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize application
$app = new MVCCore\Core\App(dirname(__DIR__));

// Configuration
$app->setConfig([
    'router_path' => dirname(__DIR__) . '/app/router',
    'views_path' => dirname(__DIR__) . '/src/Views',
    'base_url' => $_ENV['APP_URL'] ?? 'http://localhost:8000'
]);

// Run the application
$app->run();