<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$currentUser = meloverse_require_login_page();
$error = '';
$ok = '';

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    if ($title === '' || mb_strlen($title) > 200) {
        $error = 'Title is required (max 200 characters).';
    } elseif (!isset($_FILES['audio']) || !is_array($_FILES['audio'])) {
        $error = 'Please choose an audio file.';
    } else {
        $pdo = meloverse_pdo();
        $saved = null;
        try {
            $saved = meloverse_save_audio_upload($_FILES['audio']);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'INSERT INTO audio_posts (user_id, title, description, audio_path, audio_filename, mime_type)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                (int) $currentUser['id'],
                $title,
                $description !== '' ? $description : null,
                $saved['relative_path'],
                $saved['filename'],
                $saved['mime'],
            ]);
            $postId = (int) $pdo->lastInsertId();
            meloverse_sync_post_hashtags($pdo, $postId, $title . ' ' . $description);
            $pdo->prepare('UPDATE users SET posts_count = posts_count + 1 WHERE id = ?')->execute([(int) $currentUser['id']]);
            $pdo->commit();
            header('Location: post.php?id=' . $postId);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (is_array($saved) && !empty($saved['relative_path'])) {
                meloverse_delete_file_if_exists((string) $saved['relative_path']);
            }
            $error = $e->getMessage();
        }
    }
}

$pageTitle = 'Upload';
require __DIR__ . '/partials/header.php';
?>
<div class="mv-narrow mv-hover-glow">
    <h1>Upload a track</h1>
    <p class="mv-muted">MP3 or WAV only. Files are stored on this server (configure a CDN URL in <code>config.php</code> for cloud delivery).</p>
    <?php if ($error): ?><p class="mv-error"><?= meloverse_h($error) ?></p><?php endif; ?>
    <?php if ($ok): ?><p class="mv-ok"><?= meloverse_h($ok) ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="mv-form mv-upload-form">
        <label>Title
            <input type="text" name="title" required maxlength="200" value="<?= meloverse_h((string) ($_POST['title'] ?? '')) ?>">
        </label>
        <label>Description <span class="mv-muted">(optional, use #hashtags)</span>
            <textarea name="description" rows="4" maxlength="5000"><?= meloverse_h((string) ($_POST['description'] ?? '')) ?></textarea>
        </label>
        <label class="mv-dropzone" id="mv-dropzone">
            <span class="mv-dropzone__inner">
                <strong>Drop audio here</strong> or tap to choose
            </span>
            <input type="file" name="audio" accept=".mp3,.wav,audio/mpeg,audio/wav" required id="mv-audio-input">
        </label>
        <button type="submit" class="mv-btn mv-btn--block">Publish</button>
    </form>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
