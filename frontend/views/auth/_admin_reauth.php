<main class="card">
    <div class="icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    </div>
    <h1>Confirm it&rsquo;s you</h1>
    <p>Enter your administrator password. Confirmation remains valid for five minutes for refund and security-setting changes.</p>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="return" value="<?= htmlspecialchars($return) ?>">
        <label for="password">Administrator password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required autofocus>
        <button type="submit">Confirm Password</button>
    </form>
    <a class="back" href="<?= htmlspecialchars($return) ?>">Cancel</a>
</main>
