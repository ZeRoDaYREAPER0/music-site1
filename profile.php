<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

meloverse_guest_mobile_redirect();

$username = trim((string) ($_GET['u'] ?? ''));
if ($username === '' || !preg_match('/^[a-zA-Z0-9_]{1,64}$/', $username)) {
    http_response_code(404);
    echo 'User not found';
    exit;
}

$pdo = meloverse_pdo();
$stmt = $pdo->prepare(
    'SELECT id, username, display_name, bio, avatar_path, followers_count, following_count, posts_count, created_at, role, is_banned
     FROM users WHERE username = ? LIMIT 1'
);
$stmt->execute([$username]);
$profile = $stmt->fetch();
if (!$profile || (int) $profile['is_banned'] === 1) {
    http_response_code(404);
    echo 'User not found';
    exit;
}

$currentUser = meloverse_current_user();
$viewerId = $currentUser ? (int) $currentUser['id'] : 0;
$pid = (int) $profile['id'];

$following = false;
if ($viewerId > 0 && $viewerId !== $pid) {
    $f = $pdo->prepare('SELECT 1 FROM follows WHERE follower_id = ? AND following_id = ?');
    $f->execute([$viewerId, $pid]);
    $following = (bool) $f->fetch();
}

$postsStmt = $pdo->prepare(
    'SELECT p.id, p.user_id, p.title, p.description, p.audio_path, p.audio_filename, p.mime_type,
        p.duration_seconds, p.plays_count, p.created_at,
        u.username, u.display_name, u.avatar_path,
        (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) AS likes_count,
        (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comments_count'
    . ($viewerId > 0
        ? ', EXISTS(SELECT 1 FROM likes l WHERE l.post_id = p.id AND l.user_id = ' . $viewerId . ') AS liked_by_me'
        . ', EXISTS(SELECT 1 FROM bookmarks b WHERE b.post_id = p.id AND b.user_id = ' . $viewerId . ') AS bookmarked'
        : ', 0 AS liked_by_me, 0 AS bookmarked')
    . ' FROM audio_posts p
       INNER JOIN users u ON u.id = p.user_id
       WHERE p.user_id = ?
       ORDER BY p.created_at DESC
       LIMIT 80'
);
$postsStmt->execute([$pid]);
$posts = $postsStmt->fetchAll() ?: [];

$pageTitle = '@' . $profile['username'];
require __DIR__ . '/partials/header.php';
?>
<div class="mv-profile-head mv-hover-glow">
    <div class="mv-profile-avatar">
        <?php if (!empty($profile['avatar_path'])): ?>
            <img src="<?= meloverse_h(meloverse_public_storage_url((string) $profile['avatar_path'])) ?>" alt="">
        <?php else: ?>
            <span class="mv-avatar__ph lg"><?= meloverse_h(mb_strtoupper(mb_substr((string) $profile['username'], 0, 1))) ?></span>
        <?php endif; ?>
    </div>
    <div class="mv-profile-meta">
        <h1><?= meloverse_h((string) ($profile['display_name'] ?: $profile['username'])) ?></h1>
        <p class="mv-muted">@<?= meloverse_h((string) $profile['username']) ?></p>
        <?php if (!empty($profile['bio'])): ?>
            <p class="mv-profile-bio"><?= nl2br(meloverse_h((string) $profile['bio'])) ?></p>
        <?php endif; ?>
        <div class="mv-stats">
            <span><strong><?= (int) $profile['posts_count'] ?></strong> tracks</span>
            <span><strong><?= (int) $profile['followers_count'] ?></strong> followers</span>
            <span><strong><?= (int) $profile['following_count'] ?></strong> following</span>
        </div>
        <div class="mv-profile-actions">
            <?php if ($currentUser && (int) $currentUser['id'] === $pid): ?>
                <a class="mv-btn" href="profile_edit.php">Edit profile</a>
            <?php elseif ($currentUser): ?>
                <button type="button" class="mv-btn<?= $following ? ' mv-btn--secondary' : '' ?>" data-follow data-user="<?= $pid ?>"><?= $following ? 'Following' : 'Follow' ?></button>
            <?php endif; ?>
        </div>
    </div>
</div>
<div class="mv-feed">
    <h2 class="mv-section-title">Tracks</h2>
    <?php if (!$posts): ?>
        <p class="mv-empty">No uploads yet.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <?php require __DIR__ . '/partials/post_card.php'; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
