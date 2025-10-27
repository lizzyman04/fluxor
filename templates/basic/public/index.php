<?php
/**
 * MVCCore - Basic Template
 * Entry point for the application
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Initialize application
$app = new MVCCore\Core\App(dirname(__DIR__));

// Optional configuration
$app->setConfig([
    'router_path' => dirname(__DIR__) . '/app/router',
    'views_path' => dirname(__DIR__) . '/src/Views',
    'base_url' => $_ENV['APP_URL'] ?? 'http://localhost:8000'
]);

// Run the application
$app->run();