<?php
$this->extend('layouts/main');
$this->section('content');
?>

<div class="auth-container">
    <div class="auth-card">
        <h1>Login</h1>

        <?php if ($error ?? null): ?>
            <div class="alert error">
                <?= $this->e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <?= $this->csrfField() ?>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required value="<?= $this->e($this->old('email')) ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>

        <p class="auth-link">
            Don't have an account? <a href="/auth/register">Register here</a>
        </p>
    </div>
</div>

<?php $this->endSection(); ?>