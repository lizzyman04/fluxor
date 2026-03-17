# Fluxor Core 🚀

**The lightweight PHP MVC core that powers Fluxor framework** - File-based routing, elegant Flow syntax, and zero bloat.

[![Latest Stable Version](https://poser.pugx.org/lizzyman04/fluxor/v/stable)](https://packagist.org/packages/lizzyman04/fluxor)
[![Total Downloads](https://poser.pugx.org/lizzyman04/fluxor/downloads)](https://packagist.org/packages/lizzyman04/fluxor)
[![License](https://poser.pugx.org/lizzyman04/fluxor/license)](https://packagist.org/packages/lizzyman04/fluxor)
[![PHP Version Require](https://poser.pugx.org/lizzyman04/fluxor/require/php)](https://packagist.org/packages/lizzyman04/fluxor)

## 📦 What is Fluxor Core?

Fluxor Core is the **engine behind the Fluxor PHP framework** - a minimal, elegant, and powerful MVC core designed for developers who want **simplicity without sacrificing functionality**.

Unlike monolithic frameworks, Fluxor Core gives you:
- 🚀 **Blazing fast performance** (boot under 10ms)
- 📦 **Zero dependencies** beyond `phpdotenv`
- 🔍 **Transparent code** - no magic, you can read everything
- 🎯 **File-based routing** inspired by Next.js
- 💎 **Beautiful Flow syntax** for route definitions

## ✨ Core Features

| Feature | Description |
|---------|-------------|
| **🎯 File-based Routing** | Routes defined by folder structure - no route files needed |
| **💎 Flow Syntax** | Ultra-clean, chainable route definitions |
| **🔄 MVC Architecture** | Clean separation with Controllers and Views |
| **🎨 View System** | Layouts, sections, stacks, and partials |
| **🛡️ Security First** | Built-in CSRF, XSS protection, secure sessions |
| **🚦 Middleware** | Flexible request filtering (global + per-route) |
| **🎭 Error Handling** | Hierarchical error pages (404, 500, etc.) |
| **🔧 Zero Config** | Auto-detects base path and URL |

## 🚀 Quick Start (For Framework Developers)

```bash
# Add to your project
composer require lizzyman04/fluxor

# Basic usage
<?php
require 'vendor/autoload.php';

$app = new Fluxor\Core\App();
$app->run();
```

## 🏗️ Core Architecture

```
fluxor/
├── src/
│   ├── Core/
│   │   ├── App.php        # Application container
│   │   ├── Router.php     # File-based router
│   │   ├── Request.php    # HTTP request abstraction
│   │   ├── Response.php   # HTTP response builder
│   │   ├── Controller.php # Base controller
│   │   ├── View.php       # Template engine
│   │   └── Flow.php       # Elegant route syntax
│   ├── Contracts/
│   │   └── ControllerInterface.php
│   └── Exceptions/
│       └── AppException.php
```

## 💡 Core Concepts

### 1. **Application Instance**
```php
$app = new Fluxor\Core\App();
// Auto-detects base path and URL!
$basePath = $app->getBasePath();  // /var/www/my-app
$baseUrl = $app->getBaseUrl();    // http://localhost:8000/
```

### 2. **Router with Middleware**
```php
$router = $app->getRouter();

// Add middleware
$router->addMiddleware('auth', function($request) {
    if (!$request->isAuthenticated()) {
        return Response::redirect('/login');
    }
});

// Remove middleware
$router->removeMiddleware('auth');
```

### 3. **Request Object**
```php
$request->param('id');           // Route parameters
$request->input('email');         // POST/GET/JSON data
$request->all();                  // All input
$request->isJson();               // Check content type
$request->bearerToken();          // Bearer token from header
$request->validateCsrf();         // CSRF validation
$request->user();                 // Authenticated user
```

### 4. **Response Helpers**
```php
Response::json(['user' => $user]);
Response::success($data, 'Created');
Response::error('Validation failed', 422);
Response::view('profile', ['user' => $user]);
Response::redirect('/dashboard');
Response::download('/path/to/file.pdf');
```

### 5. **Flow Syntax (The Star of the Show)**
```php
use Fluxor\Flow;

// In your route file
Flow::GET()->do(fn($req) => 'Hello World');
Flow::POST()->to(UserController::class, 'store');
Flow::PUT()->do(function($req) { /* ... */ });
Flow::DELETE()->to(ProductController::class, 'destroy');
Flow::any(fn($req) => Response::json(['method' => $req->method]));
Flow::use(fn($req) => $req->isAuthenticated() ? null : Response::redirect('/login'));

return Flow::execute($req);
```

### 6. **View System**
```php
// In controller
return Response::view('home', ['title' => 'Home']);

// In view (home.php)
$this->extend('layouts/main');
$this->section('content');
    <h1><?= $this->e($title) ?></h1>
$this->endSection();

// Partials
$this->include('components/alert', ['type' => 'success']);
```

## 🛠️ Configuration

Fluxor Core automatically detects:
- ✅ **Base Path** - Works in both development and production
- ✅ **Base URL** - Detects protocol, host, and subdirectory
- ✅ **Environment** - From `.env` or defaults

Minimal `.env` options:
```env
APP_ENV=development
APP_DEBUG=true
APP_TIMEZONE=UTC
```

## 🔧 Advanced Usage

### Custom Configuration
```php
$app->setConfig([
    'router_path' => __DIR__ . '/custom/router',
    'views_path' => __DIR__ . '/resources/views',
    'storage_path' => __DIR__ . '/storage',
]);
```

### Service Registration
```php
$app->registerService('mailer', new CustomMailer());
$mailer = $app->getService('mailer');
```

### Error Handling
```php
// Hierarchical error pages
app/router/
├── 404.php           # Global 404
├── api/
│   └── 404.php       # API-specific 404
└── admin/
    └── 403.php       # Admin-specific 403
```

## 📊 Performance

Fluxor Core is built for speed:
- **Boot time**: < 10ms
- **Memory footprint**: ~2MB
- **Zero magic** - no reflection overhead
- **File-based routing** - no route caching needed

## 🤝 Extending Fluxor

Build your own features on top of the core:
```php
// Create custom middleware
class ThrottleMiddleware {
    public function handle($request, $next) {
        // Your logic
        return $next($request);
    }
}

// Register with router
$router->addMiddleware('throttle', [new ThrottleMiddleware, 'handle']);
```

## 📚 Documentation

For complete documentation, including:
- [Creating your first Fluxor app](https://github.com/lizzyman04/fluxor-php)
- [Template skeletons](https://github.com/lizzyman04/fluxor-php)
- [API reference](https://github.com/lizzyman04/fluxor/wiki)

## 🎯 Who is this for?

Fluxor Core is perfect for:
- **Framework authors** building custom solutions
- **API developers** who want minimal overhead
- **MVC learners** who want to understand internals
- **Performance purists** who hate bloat
- **PHP artisans** who appreciate elegant code

## 📄 License

Fluxor Core is open-source software licensed under the [MIT license](LICENSE).

## 🙏 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md).

---

**Fluxor** - Build elegant PHP applications with joy! 🎉