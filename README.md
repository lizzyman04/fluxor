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

## 🚀 Quick Start

```bash
# Add Fluxor Core to your project
composer require lizzyman04/fluxor

# Basic usage
<?php
require 'vendor/autoload.php';

$app = new Fluxor\App();
$app->run();
```

## 🏗️ Core Architecture

```
fluxor/
├── src/
│   ├── Core/
│   │   ├── App.php              # Application facade
│   │   ├── Controller.php       # Base controller
│   │   ├── View.php             # View engine
│   │   ├── App/                 # Application internals
│   │   │   ├── Application.php
│   │   │   ├── Config.php
│   │   │   ├── Environment.php
│   │   │   └── ExceptionHandler.php
│   │   ├── Foundation/          # Service container
│   │   │   ├── ServiceContainer.php
│   │   │   └── ServiceProvider.php
│   │   ├── Http/                # HTTP layer
│   │   │   ├── Request.php
│   │   │   ├── Response.php
│   │   │   └── Router.php
│   │   ├── Routing/             # Flow syntax
│   │   │   └── Flow.php
│   │   └── View/                # View components
│   │       ├── ViewFactory.php
│   │       └── Compilers/
│   │           └── PhpCompiler.php
│   ├── Contracts/
│   │   └── ControllerInterface.php
│   ├── Exceptions/
│   │   ├── AppException.php
│   │   ├── HttpException.php
│   │   ├── NotFoundException.php
│   │   └── ValidationException.php
│   ├── Helpers/
│   │   ├── Functions.php
│   │   ├── HttpStatusCode.php
│   │   └── Str.php
│   └── Fluxor.php                # Re-exports for clean API
```

## 🎯 Zero Config Import

All core classes are automatically re-exported for cleaner code:

```php
use Fluxor\App;
use Fluxor\Request;
use Fluxor\Flow;
```

## 💡 Core Concepts

### Application Instance
```php
$app = new Fluxor\App();
$basePath = $app->getBasePath();  // Auto-detected!
$baseUrl = $app->getBaseUrl();    // Auto-detected!
```

### Router with Middleware
```php
$router = $app->getRouter();
$router->addMiddleware('auth', function($request) {
    if (!$request->isAuthenticated()) {
        return Fluxor\Response::redirect('/login');
    }
});
```

### Request & Response
```php
$id = $request->param('id');
$email = $request->input('email');
$token = $request->bearerToken();

return Fluxor\Response::json(['user' => $user]);
return Fluxor\Response::view('profile', ['user' => $user]);
```

### Flow Syntax
```php
use Fluxor\Flow;

Flow::GET()->do(fn($req) => 'Hello World');
Flow::POST()->to(UserController::class, 'store');
Flow::use(fn($req) => $req->isAuthenticated() ? null : redirect('/login'));

return Flow::execute($req);
```

### View System
```php
// In controller
return Fluxor\Response::view('home', ['title' => 'Home']);

// In view (home.php)
$this->extend('layouts/main');
$this->section('content');
    <h1><?= $this->e($title) ?></h1>
$this->endSection();
```

## 📊 Performance

- **Boot time**: < 10ms
- **Memory footprint**: ~2MB
- **Zero magic** - no reflection overhead
- **File-based routing** - no route caching needed

## 📚 Documentation

For complete documentation, tutorials, and examples, visit:
👉 [**Fluxor PHP Framework**](https://github.com/lizzyman04/fluxor-php)

The `fluxor-php` repository contains:
- Step-by-step tutorials
- Complete application examples
- Template guides (basic, mvc, api)
- Database configuration
- Production deployment
- API reference

## 🎯 Who is this for?

- **Framework authors** building custom solutions
- **API developers** who want minimal overhead
- **MVC learners** who want to understand internals
- **Performance purists** who hate bloat

## 📄 License

MIT License - see [LICENSE](LICENSE) file for details.

---

**Fluxor** - Build elegant PHP applications with joy! 🎉