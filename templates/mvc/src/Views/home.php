<?php
$this->extend('layouts/main');
$this->section('content');
?>

<div class="hero">
    <div class="container">
        <h1>Welcome to MVCCore MVC 🚀</h1>
        <p class="lead">A powerful PHP MVC framework with file-based routing</p>
        
        <?php if ($user ?? null): ?>
            <div class="alert success">
                You are logged in as <strong><?= $this->e($user['name']) ?></strong>
            </div>
            <a href="/dashboard" class="btn btn-primary">Go to Dashboard</a>
        <?php else: ?>
            <div class="cta-buttons">
                <a href="/auth/login" class="btn btn-primary">Login</a>
                <a href="/auth/register" class="btn btn-secondary">Register</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="features">
    <div class="container">
        <h2>Features</h2>
        <div class="feature-grid">
            <?php foreach ($features as $feature): ?>
                <div class="feature-card">
                    <h3><?= $this->e($feature) ?></h3>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>