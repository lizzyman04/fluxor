<?php

namespace Source\Controllers;

use MVCCore\Controller;
use MVCCore\Core\Response;
use MVCCore\Core\View;
use Source\Models\User;

class AuthController extends Controller
{
    public function showLogin()
    {
        return Response::view('auth/login', [
            'title' => 'Login',
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    public function showRegister()
    {
        return Response::view('auth/register', [
            'title' => 'Register',
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    public function login()
    {
        // Validate CSRF token
        if (!$this->request->validateCsrf()) {
            return Response::error('Invalid CSRF token', 419);
        }

        // Validate input
        $credentials = $this->request->only(['email', 'password']);
        
        if (empty($credentials['email']) || empty($credentials['password'])) {
            return Response::view('auth/login', [
                'title' => 'Login',
                'error' => 'Email and password are required',
                'csrf_token' => $this->generateCsrfToken()
            ]);
        }

        // Attempt login (simulated)
        $user = $this->attemptLogin($credentials);
        
        if ($user) {
            $this->request->session('user', $user);
            return Response::redirect('/dashboard');
        }

        return Response::view('auth/login', [
            'title' => 'Login',
            'error' => 'Invalid credentials',
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    public function register()
    {
        // Validate CSRF token
        if (!$this->request->validateCsrf()) {
            return Response::error('Invalid CSRF token', 419);
        }

        $userData = $this->request->only(['name', 'email', 'password']);
        
        // Basic validation
        if (empty($userData['name']) || empty($userData['email']) || empty($userData['password'])) {
            return Response::view('auth/register', [
                'title' => 'Register',
                'error' => 'All fields are required',
                'csrf_token' => $this->generateCsrfToken()
            ]);
        }

        // Create user (simulated)
        $user = $this->createUser($userData);
        
        if ($user) {
            $this->request->session('user', $user);
            return Response::redirect('/dashboard');
        }

        return Response::view('auth/register', [
            'title' => 'Register',
            'error' => 'Registration failed',
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    public function logout()
    {
        $this->request->session('user', null);
        session_destroy();
        
        return Response::redirect('/');
    }

    private function generateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->request->session('csrf_token', $token);
        return $token;
    }

    private function attemptLogin(array $credentials): ?array
    {
        // Simulated login - replace with real authentication
        if ($credentials['email'] === 'admin@example.com' && $credentials['password'] === 'password') {
            return [
                'id' => 1,
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'role' => 'admin'
            ];
        }
        
        return null;
    }

    private function createUser(array $userData): ?array
    {
        // Simulated user creation - replace with real database logic
        return [
            'id' => rand(1000, 9999),
            'name' => $userData['name'],
            'email' => $userData['email'],
            'role' => 'user'
        ];
    }
}