<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$currentUser = meloverse_require_login_page();
$uid = (int) $currentUser['id'];
$pdo = meloverse_pdo();

if (!empty($_GET['readall'])) {
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$uid]);
    header('Location: notifications.php');
    exit;
}

if (isset($_GET['mark'])) {
    $mid = (int) $_GET['mark'];
    if ($mid > 0) {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$mid, $uid]);
    }
    header('Location: notifications.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT n.id, n.type, n.is_read, n.created_at, n.post_id,
        a.username AS actor_username, a.display_name AS actor_display
     FROM notifications n
     INNER JOIN users a ON a.id = n.actor_id
     WHERE n.user_id = ?
     ORDER BY n.created_at DESC
     LIMIT 100'
);
$stmt->execute([$uid]);
$rows = $stmt->fetchAll() ?: [];

$pageTitle = 'Notifications';
require __DIR__ . '/partials/header.php';
?>
<div class="mv-narrow">
    <div class="mv-row-spaced">
        <h1>Notifications</h1>
        <?php if ($rows): ?>
            <a class="mv-btn mv-btn--sm mv-btn--secondary" href="notifications.php?readall=1">Mark all read</a>
        <?php endif; ?>
    </div>
    <?php if (!$rows): ?>
        <p class="mv-muted">You are all caught up.</p>
    <?php else: ?>
        <ul class="mv-notif-list">
            <?php foreach ($rows as $n): ?>
                <li class="mv-notif-item mv-hover-glow<?= (int) $n['is_read'] === 0 ? ' is-unread' : '' ?>">
                    <?php
                    $actor = (string) ($n['actor_display'] ?: $n['actor_username']);
                    $pid = $n['post_id'] !== null ? (int) $n['post_id'] : 0;
                    $type = (string) $n['type'];
                    $text = match ($type) {
                        'like' => meloverse_h($actor) . ' liked your track.',
                        'comment' => meloverse_h($actor) . ' commented on your track.',
                        'follow' => meloverse_h($actor) . ' followed you.',
                        default => meloverse_h($actor) . ' interacted with your content.',
                    };
                    ?>
                    <p><?= $text ?></p>
                    <div class="mv-notif-meta">
                        <time class="mv-muted" datetime="<?= meloverse_h((string) $n['created_at']) ?>"><?= meloverse_h(date('M j, g:i A', strtotime((string) $n['created_at']))) ?></time>
                        <?php if ($pid > 0 && in_array($type, ['like', 'comment'], true)): ?>
                            <a href="post.php?id=<?= $pid ?>">View post</a>
                        <?php elseif ($type === 'follow'): ?>
                            <a href="profile.php?u=<?= meloverse_h((string) $n['actor_username']) ?>">View profile</a>
                        <?php endif; ?>
                        <?php if ((int) $n['is_read'] === 0): ?>
                            <a class="mv-linkbtn" href="notifications.php?mark=<?= (int) $n['id'] ?>">Mark read</a>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
