<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

meloverse_guest_mobile_redirect();

$q = trim((string) ($_GET['q'] ?? ''));
$users = [];
$posts = [];
$currentUser = meloverse_current_user();
$viewerId = $currentUser ? (int) $currentUser['id'] : 0;

if ($q !== '') {
    $pdo = meloverse_pdo();
    $esc = static function (string $s): string {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    };
    $like = '%' . $esc($q) . '%';
    $uStmt = $pdo->prepare(
        'SELECT username, display_name, bio, avatar_path, followers_count
         FROM users
         WHERE is_banned = 0 AND (username LIKE ? ESCAPE \'\\\\\' OR display_name LIKE ? ESCAPE \'\\\\\')
         ORDER BY followers_count DESC
         LIMIT 30'
    );
    $uStmt->execute([$like, $like]);
    $users = $uStmt->fetchAll() ?: [];

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
        WHERE (p.title LIKE ? ESCAPE \'\\\\\' OR p.description LIKE ? ESCAPE \'\\\\\')
        ORDER BY p.created_at DESC
        LIMIT 40';
    $pStmt = $pdo->prepare($sql);
    $pStmt->execute([$like, $like]);
    $posts = $pStmt->fetchAll() ?: [];
}

$pageTitle = 'Search';
require __DIR__ . '/partials/header.php';
?>
<div class="mv-narrow">
    <form class="mv-searchbar mv-hover-glow" method="get" action="search.php">
        <input type="search" name="q" value="<?= meloverse_h($q) ?>" placeholder="Search people or tracks…" autocomplete="off">
        <button type="submit" class="mv-btn">Search</button>
    </form>
    <?php if ($q === ''): ?>
        <p class="mv-muted">Try a name, username, or track title.</p>
    <?php else: ?>
        <section class="mv-search-section">
            <h2>People</h2>
            <?php if (!$users): ?>
                <p class="mv-muted">No users found.</p>
            <?php else: ?>
                <ul class="mv-user-list">
                    <?php foreach ($users as $u): ?>
                        <li class="mv-user-row mv-hover-glow">
                            <a href="profile.php?u=<?= meloverse_h((string) $u['username']) ?>">
                                <?php if (!empty($u['avatar_path'])): ?>
                                    <img class="mv-avatar sm" src="<?= meloverse_h(meloverse_public_storage_url((string) $u['avatar_path'])) ?>" alt="">
                                <?php else: ?>
                                    <span class="mv-avatar sm mv-avatar__ph"><?= meloverse_h(mb_strtoupper(mb_substr((string) $u['username'], 0, 1))) ?></span>
                                <?php endif; ?>
                                <span>
                                    <span class="mv-card__name"><?= meloverse_h((string) ($u['display_name'] ?: $u['username'])) ?></span>
                                    <span class="mv-muted">@<?= meloverse_h((string) $u['username']) ?> · <?= (int) $u['followers_count'] ?> followers</span>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <section class="mv-search-section">
            <h2>Tracks</h2>
            <?php if (!$posts): ?>
                <p class="mv-muted">No tracks found.</p>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <?php require __DIR__ . '/partials/post_card.php'; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
