<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$currentUser = meloverse_require_login_page();
$uid = (int) $currentUser['id'];
$pdo = meloverse_pdo();

$sql = 'SELECT p.id, p.user_id, p.title, p.description, p.audio_path, p.audio_filename, p.mime_type,
    p.duration_seconds, p.plays_count, p.created_at,
    u.username, u.display_name, u.avatar_path,
    (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) AS likes_count,
    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comments_count,
    EXISTS(SELECT 1 FROM likes l WHERE l.post_id = p.id AND l.user_id = ' . $uid . ') AS liked_by_me,
    1 AS bookmarked
    FROM bookmarks b
    INNER JOIN audio_posts p ON p.id = b.post_id
    INNER JOIN users u ON u.id = p.user_id AND u.is_banned = 0
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
    LIMIT 80';
$stmt = $pdo->prepare($sql);
$stmt->execute([$uid]);
$posts = $stmt->fetchAll() ?: [];

$pageTitle = 'Saved';
require __DIR__ . '/partials/header.php';
?>
<div class="mv-feed">
    <h1 class="mv-section-title">Saved tracks</h1>
    <?php if (!$posts): ?>
        <p class="mv-empty">Nothing saved yet. Use the star on any post.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <?php require __DIR__ . '/partials/post_card.php'; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
