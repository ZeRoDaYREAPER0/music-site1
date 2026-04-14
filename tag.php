<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

meloverse_guest_mobile_redirect();

$tag = trim((string) ($_GET['h'] ?? ''));
$tag = mb_strtolower($tag);
if ($tag === '' || mb_strlen($tag) > 100) {
    http_response_code(404);
    echo 'Invalid tag';
    exit;
}

$currentUser = meloverse_current_user();
$viewerId = $currentUser ? (int) $currentUser['id'] : 0;
$pdo = meloverse_pdo();
$h = $pdo->prepare('SELECT id FROM hashtags WHERE tag = ? LIMIT 1');
$h->execute([$tag]);
$hid = (int) $h->fetchColumn();
$posts = [];
if ($hid > 0) {
    $sql = 'SELECT p.id, p.user_id, p.title, p.description, p.audio_path, p.audio_filename, p.mime_type,
        p.duration_seconds, p.plays_count, p.created_at,
        u.username, u.display_name, u.avatar_path,
        (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) AS likes_count,
        (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comments_count';
    if ($viewerId > 0) {
        $sql .= ', EXISTS(SELECT 1 FROM likes l WHERE l.post_id = p.id AND l.user_id = ' . $viewerId . ') AS liked_by_me';
        $sql .= ', EXISTS(SELECT 1 FROM bookmarks b WHERE b.post_id = p.id AND b.user_id = ' . $viewerId . ') AS bookmarked';
    } else {
        $sql .= ', 0 AS liked_by_me, 0 AS bookmarked';
    }
    $sql .= ' FROM audio_posts p
        INNER JOIN users u ON u.id = p.user_id AND u.is_banned = 0
        INNER JOIN post_hashtags ph ON ph.post_id = p.id AND ph.hashtag_id = ?
        ORDER BY p.created_at DESC
        LIMIT 60';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$hid]);
    $posts = $stmt->fetchAll() ?: [];
}

$pageTitle = '#' . $tag;
require __DIR__ . '/partials/header.php';
?>
<div class="mv-feed">
    <h1 class="mv-section-title">#<?= meloverse_h($tag) ?></h1>
    <?php if (!$posts): ?>
        <p class="mv-empty">No posts with this hashtag yet.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <?php require __DIR__ . '/partials/post_card.php'; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
