<?php
/**
 * Register routes - GET/POST /auth/register
 */

use MVCCore\Flow;
use Source\Controllers\AuthController;

// Middleware to redirect if already authenticated
Flow::use(function ($req) {
    if ($req->isAuthenticated()) {
        return Response::redirect('/dashboard');
    }
});

Flow::GET()->to(AuthController::class, 'showRegister');
Flow::POST()->to(AuthController::class, 'register');

return Flow::execute($req);