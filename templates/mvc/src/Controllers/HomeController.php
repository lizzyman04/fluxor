<?php

namespace Source\Controllers;

use MVCCore\Controller;
use MVCCore\Core\Response;
use MVCCore\Core\View;

class HomeController extends Controller
{
    public function index()
    {
        return Response::view('home', [
            'title' => 'Welcome to MVCCore MVC',
            'user' => $this->request->user(),
            'features' => [
                'File-based routing like Next.js',
                'Elegant Flow syntax',
                'Powerful View system',
                'Middleware support',
                'Error handling hierarchy'
            ]
        ]);
    }

    public function about()
    {
        return Response::view('about', [
            'title' => 'About Us',
            'page' => 'about'
        ]);
    }

    public function dashboard()
    {
        // Require authentication
        if (!$this->request->isAuthenticated()) {
            return Response::redirect('/auth/login');
        }

        return Response::view('dashboard', [
            'title' => 'Dashboard',
            'user' => $this->request->user()
        ]);
    }
}