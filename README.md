# MVCCore 🚀

A lightweight, elegant PHP MVC framework with Next.js-style file-based routing and beautiful Flow syntax.

## ✨ Features

- **🎯 File-based Routing** - Like Next.js App Router
- **💎 Elegant Flow Syntax** - Ultra clean route definitions  
- **🔄 MVC Architecture** - Clean separation of concerns
- **🎨 Powerful View System** - Layouts, sections, and stacks
- **🛡️ Built-in Security** - CSRF protection, validation
- **🚦 Middleware Support** - Flexible request processing
- **🎭 Error Handling** - Hierarchical error pages
- **📦 Multiple Templates** - Basic, MVC, and API starters

## Quick Start

```bash
composer create-project lizzyman04/mvccore my-app
cd my-app
php -S localhost:8000 -t public
```

Visit `http://localhost:8000`

## 🎯 Elegant Routing

Create routes using the file system:

```
app/router/
├── page.php                 # GET /
├── api/
│   └── hello/
│       └── index.php       # GET /api/hello  
└── posts/
    └── [slug]/
        └── index.php       # GET /posts/{slug}
```

### Example Route

```php
<?php
// app/router/posts/[slug]/index.php

use MVCCore\Flow;
use MVCCore\Core\Response;

Flow::GET()->do(fn($req) => 
    Response::success(['slug' => $req->param('slug')])
);

Flow::PUT()->to(PostController::class, 'update');

return Flow::execute($req);
```

## 💎 Beautiful Flow Syntax

```php
// Ultra clean route definitions
Flow::GET()->do(fn($req) => Response::json(['hello' => 'world']));
Flow::POST()->to(Controller::class, 'store');
Flow::use(fn($req) => $req->isAuthenticated() ? null : Response::redirect('/login'));
```

## 🏗️ Three Template Options

### 1. Basic Template
```bash
composer create-project lizzyman04/mvccore my-app
# Choose "basic"
```

### 2. MVC Template (Authentication + Views)
```bash
composer create-project lizzyman04/mvccore my-app
# Choose "mvc"
```

### 3. API Template (RESTful API)
```bash
composer create-project lizzyman04/mvccore my-app  
# Choose "api"
```

## 📖 Documentation

### Routing
Create files in `app/router/` to define routes:

- `page.php` → `/`
- `about.php` → `/about` 
- `posts/index.php` → `/posts`
- `posts/[slug]/index.php` → `/posts/any-slug`
- `(auth)/login/index.php` → `/auth/login`

### Flow Methods
```php
Flow::GET()->do(fn($req) => ...);
Flow::POST()->to(Controller::class, 'method');
Flow::PUT()->do(function($req) { ... });
Flow::DELETE()->to(Controller::class);
Flow::any(fn($req) => ...);
Flow::use(middleware);
```

### Response Types
```php
Response::success($data, 'Message');
Response::error('Error message', 400);
Response::view('template', $data);
Response::json($data);
Response::redirect('/path');
```

### Views with Layouts
```php
// In controller
return Response::view('home', ['title' => 'Home']);

// In view template
$this->extend('layouts/main');
$this->section('content');
// Your content
$this->endSection();
```

## 🛠️ Configuration

Create `.env` file:
```env
APP_NAME="My MVCCore App"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000
```

## 🎨 Custom Error Pages

Create hierarchical error handlers:
```
app/router/
├── not-found.php           # Global 404
├── 404.php                # Alternative 404
├── api/
│   └── 404.php            # API-specific 404
└── (auth)/
    └── 401.php            # Auth-specific 401
```

## 📦 Installation

### Via Composer
```bash
composer require lizzyman04/mvccore
```

### Manual Installation
```bash
git clone https://github.com/lizzyman04/mvccore.git
cd mvccore
composer install
```

## 🔧 Development

Run tests:
```bash
composer test
```

Run with coverage:
```bash
composer test-coverage
```

## 🤝 Contributing

We welcome contributions! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

## 📄 License

MIT License - see [LICENSE](LICENSE) file for details.

## 🆕 Changelog

### v1.0.0
- Initial release
- File-based routing system
- Elegant Flow syntax
- Three starter templates
- Hierarchical error handling

---

**MVCCore** - Build elegant PHP applications with joy! 🎉