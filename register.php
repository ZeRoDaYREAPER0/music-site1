<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

if (meloverse_current_user()) {
    header('Location: index.php');
    exit;
}

$error = '';
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $display = trim((string) ($_POST['display_name'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    $pass2 = (string) ($_POST['password_confirm'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username)) {
        $error = 'Username must be 3–32 characters: letters, numbers, underscore.';
    } elseif (mb_strlen($display) > 120) {
        $error = 'Display name is too long.';
    } elseif (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass !== $pass2) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        try {
            $stmt = meloverse_pdo()->prepare(
                'INSERT INTO users (email, username, password_hash, display_name) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([
                $email,
                $username,
                $hash,
                $display !== '' ? $display : $username,
            ]);
            meloverse_login_user((int) meloverse_pdo()->lastInsertId());
            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                $error = 'Email or username is already taken.';
            } else {
                $error = 'Could not register. Try again.';
            }
        }
    }
}

$pageTitle = 'Sign up';
$currentUser = null;
require __DIR__ . '/partials/header.php';
?>
<div class="mv-auth">
    <div class="mv-auth__panel mv-hover-glow">
        <h1>Create your MELOVERSE</h1>
        <?php if ($error): ?><p class="mv-error"><?= meloverse_h($error) ?></p><?php endif; ?>
        <form method="post" class="mv-form">
            <label>Email
                <input type="email" name="email" required autocomplete="email" value="<?= meloverse_h((string) ($_POST['email'] ?? '')) ?>">
            </label>
            <label>Username
                <input type="text" name="username" required pattern="[a-zA-Z0-9_]{3,32}" autocomplete="username" value="<?= meloverse_h((string) ($_POST['username'] ?? '')) ?>">
            </label>
            <label>Display name <span class="mv-muted">(optional)</span>
                <input type="text" name="display_name" maxlength="120" value="<?= meloverse_h((string) ($_POST['display_name'] ?? '')) ?>">
            </label>
            <label>Password
                <input type="password" name="password" required minlength="8" autocomplete="new-password">
            </label>
            <label>Confirm password
                <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
            </label>
            <button type="submit" class="mv-btn mv-btn--block">Sign up</button>
        </form>
        <p class="mv-muted">Already have an account? <a href="login.php">Log in</a></p>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
