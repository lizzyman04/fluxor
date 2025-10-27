<?php
/**
 * Login routes - GET/POST /auth/login
 */

use MVCCore\Flow;
use Source\Controllers\AuthController;

// Middleware to redirect if already authenticated
Flow::use(function ($req) {
    if ($req->isAuthenticated()) {
        return Response::redirect('/dashboard');
    }
});

Flow::GET()->to(AuthController::class, 'showLogin');
Flow::POST()->to(AuthController::class, 'login');

return Flow::execute($req);