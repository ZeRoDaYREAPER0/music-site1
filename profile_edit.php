<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$currentUser = meloverse_require_login_page();
$fresh = meloverse_pdo()
    ->prepare('SELECT bio, avatar_path FROM users WHERE id = ? LIMIT 1');
$fresh->execute([(int) $currentUser['id']]);
$row = $fresh->fetch() ?: [];
$currentUser['bio'] = $row['bio'] ?? $currentUser['bio'];
$currentUser['avatar_path'] = $row['avatar_path'] ?? $currentUser['avatar_path'];
$error = '';

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $bio = trim((string) ($_POST['bio'] ?? ''));
    if (mb_strlen($bio) > 2000) {
        $error = 'Bio is too long.';
    } else {
        $avatarPath = $currentUser['avatar_path'] ?? null;
        try {
            if (!empty($_FILES['avatar']['name'])) {
                $new = meloverse_save_avatar_upload($_FILES['avatar']);
                if ($avatarPath) {
                    meloverse_delete_file_if_exists((string) $avatarPath);
                }
                $avatarPath = $new;
            }
            $stmt = meloverse_pdo()->prepare('UPDATE users SET bio = ?, avatar_path = ? WHERE id = ?');
            $stmt->execute([
                $bio !== '' ? $bio : null,
                $avatarPath,
                (int) $currentUser['id'],
            ]);
            header('Location: profile.php?u=' . rawurlencode((string) $currentUser['username']));
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$pageTitle = 'Edit profile';
require __DIR__ . '/partials/header.php';
?>
<div class="mv-narrow mv-hover-glow">
    <h1>Edit profile</h1>
    <?php if ($error): ?><p class="mv-error"><?= meloverse_h($error) ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="mv-form">
        <label>Profile photo
            <input type="file" name="avatar" accept="image/*">
        </label>
        <label>Bio
            <textarea name="bio" rows="5" maxlength="2000"><?= meloverse_h((string) ($currentUser['bio'] ?? '')) ?></textarea>
        </label>
        <button type="submit" class="mv-btn mv-btn--block">Save</button>
    </form>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
