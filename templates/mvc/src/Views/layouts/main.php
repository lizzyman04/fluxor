<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->e($title ?? 'MVCCore MVC') ?></title>
    <link rel="stylesheet" href="<?= $this->asset('css/app.css') ?>">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <a href="/">MVCCore MVC</a>
            </div>
            <div class="nav-menu">
                <?php if ($user ?? null): ?>
                    <span>Welcome, <?= $this->e($user['name']) ?></span>
                    <a href="/dashboard">Dashboard</a>
                    <a href="/auth/logout">Logout</a>
                <?php else: ?>
                    <a href="/auth/login">Login</a>
                    <a href="/auth/register">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <?= $this->yield('content') ?>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> MVCCore MVC. All rights reserved.</p>
        </div>
    </footer>

    <script src="<?= $this->asset('js/app.js') ?>"></script>
</body>
</html>