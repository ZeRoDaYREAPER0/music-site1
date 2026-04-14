<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

if (meloverse_current_user()) {
    header('Location: index.php');
    exit;
}

$error = '';
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $id = trim((string) ($_POST['identifier'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    if ($id === '' || $pass === '') {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = meloverse_pdo()->prepare(
            'SELECT id, password_hash, is_banned FROM users WHERE email = ? OR username = ? LIMIT 1'
        );
        $stmt->execute([$id, $id]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($pass, (string) $row['password_hash'])) {
            $error = 'Invalid credentials.';
        } elseif ((int) $row['is_banned'] === 1) {
            $error = 'This account is suspended.';
        } else {
            meloverse_login_user((int) $row['id']);
            $next = meloverse_safe_redirect_path((string) ($_POST['next'] ?? $_GET['next'] ?? 'index.php'));
            header('Location: ' . $next);
            exit;
        }
    }
}

$pageTitle = 'Log in';
$currentUser = null;
require __DIR__ . '/partials/header.php';
?>
<div class="mv-auth">
    <div class="mv-auth__panel mv-hover-glow">
        <h1>Welcome back</h1>
        <?php if (!empty($_GET['guest'])): ?>
            <p class="mv-banner">On mobile, sign in to browse and interact with MELOVERSE.</p>
        <?php endif; ?>
        <?php if ($error): ?><p class="mv-error"><?= meloverse_h($error) ?></p><?php endif; ?>
        <form method="post" class="mv-form">
            <input type="hidden" name="next" value="<?= meloverse_h((string) ($_GET['next'] ?? '')) ?>">
            <label>Email or username
                <input type="text" name="identifier" required autocomplete="username" value="<?= meloverse_h((string) ($_POST['identifier'] ?? '')) ?>">
            </label>
            <label>Password
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button type="submit" class="mv-btn mv-btn--block">Log in</button>
        </form>
        <p class="mv-muted">New here? <a href="register.php">Create an account</a></p>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
