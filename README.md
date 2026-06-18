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
- 📦 **Minimal dependencies** - routing is powered by the standalone, zero-dependency [`lizzyman04/file-router`](https://github.com/lizzyman04/file-router) package; nothing else
- 🔍 **Transparent code** - no magic, you can read everything
- 🎯 **File-based routing** inspired by Next.js
- 💎 **Elegant Flow syntax** for route definitions
- 🔒 **Config locking** to protect critical settings
- 🌐 **Built-in CORS support** (global + per-route)

## 🚀 Quick Start

```bash
composer require lizzyman04/fluxor
```

```php
<?php
require 'vendor/autoload.php';

$app = new Fluxor\Core\App();
$app->run();
```

## 🎯 Imports

Import core classes from their namespaces (PSR-4 autoloaded):

```php
use Fluxor\Core\App;
use Fluxor\Core\Http\Request;
use Fluxor\Core\Http\Response;
use Fluxor\Core\Routing\Flow;
use Fluxor\Core\View;
```

> **Breaking change in 2.0:** the short-name aliases `Fluxor\Flow`, `Fluxor\App`,
> `Fluxor\Response`, etc. were removed. Use the fully-qualified namespaces above.

## 💡 Core Concepts

### Application Instance
```php
$app = new Fluxor\Core\App();
$basePath = $app->getBasePath();  // Auto-detected!
$baseUrl = $app->getBaseUrl();    // Auto-detected!
```

### Global CORS Configuration
```php
$app = new Fluxor\Core\App();
$app->cors()->allowOrigin('*')->enable();
$app->run();
```

### File-based Routing
The directory structure is the route table. URL matching (dynamic `[id]`,
catch-all `[...slug]`, route groups, the compiled-route cache) is handled by
the standalone [`lizzyman04/file-router`](https://github.com/lizzyman04/file-router)
engine; Fluxor adds the `Flow` syntax and dispatch on top. The route-file
syntax below is unchanged.

```php
// app/router/users/[id].php
use Fluxor\Core\Routing\Flow;
use Fluxor\Core\Http\Response;

Flow::GET()->do(function($req) {
    $userId = $req->param('id');
    return Response::success(['user' => $userId]);
});
```

### Router with Middleware
```php
$router = $app->getRouter();
$router->addMiddleware('auth', function($request) {
    if (!$request->isAuthenticated()) {
        return Fluxor\Core\Http\Response::redirect('/login');
    }
});
```

### Request & Response
```php
use Fluxor\Core\Http\Response;

$id = $request->param('id');
$email = $request->input('email');
$token = $request->bearerToken();

return Response::json(['user' => $user]);
return Response::view('profile', ['user' => $user]);
```

### Flow Syntax
```php
use Fluxor\Core\Routing\Flow;

// Simple route
Flow::GET()->do(fn($req) => 'Hello World');

// Controller binding
Flow::POST()->to(UserController::class, 'store');

// Middleware
Flow::use(fn($req) => $req->isAuthenticated() ? null : redirect('/login'));
```

### View System
```php
// In controller
return Response::view('home', ['title' => 'Home']);

// In view (home.php)
View::extend('layouts/main');
View::section('content');
    <h1><?= View::e($title) ?></h1>
View::endSection();
```

### Global Helpers
```php
// Environment variables
$debug = env('APP_DEBUG', false);
$dbName = env('DB_NAME', 'database');

// Path helpers
$root = base_path();
$url = base_url('api/users');
$asset = asset('css/app.css');

// HTTP helpers
abort(404, 'Not Found');
return redirect('/dashboard');

// Debug helpers
dump($user);
dd($data);  // Dump and die
```

## 📚 Documentation

**Full documentation available at:** 👉 [**https://fluxor.tudocomlizzyman.com**](https://fluxor.tudocomlizzyman.com)

The documentation includes:
- Installation guide
- File-based routing
- Flow syntax reference
- Views and layouts
- Controllers and middleware
- Environment configuration
- Complete API reference with helper functions

## 📄 License

MIT License - see [LICENSE](LICENSE) file for details.

---

**Fluxor** - Build elegant PHP applications with joy! 🎉