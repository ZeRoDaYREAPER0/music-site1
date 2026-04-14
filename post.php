<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

meloverse_guest_mobile_redirect();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$currentUser = meloverse_current_user();
$viewerId = $currentUser ? (int) $currentUser['id'] : 0;
$pdo = meloverse_pdo();

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
$sql .= ' FROM audio_posts p INNER JOIN users u ON u.id = p.user_id WHERE p.id = ? AND u.is_banned = 0 LIMIT 1';

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$post = $stmt->fetch();
if (!$post) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$cstmt = $pdo->prepare(
    'SELECT c.id, c.parent_id, c.body, c.created_at, c.user_id, u.username, u.display_name
     FROM comments c
     INNER JOIN users u ON u.id = c.user_id AND u.is_banned = 0
     WHERE c.post_id = ?
     ORDER BY c.created_at ASC'
);
$cstmt->execute([$id]);
$flat = $cstmt->fetchAll() ?: [];

$byParent = [];
foreach ($flat as $c) {
    $pid = $c['parent_id'] !== null ? (int) $c['parent_id'] : 0;
    $byParent[$pid][] = $c;
}

function meloverse_render_comments(array $byParent, int $parentId, int $depth = 0): void
{
    if (empty($byParent[$parentId])) {
        return;
    }
    echo '<ul class="mv-comment-list" data-depth="' . (int) $depth . '">';
    foreach ($byParent[$parentId] as $c) {
        $cid = (int) $c['id'];
        echo '<li id="c-' . $cid . '" class="mv-comment">';
        echo '<div class="mv-comment__head"><strong>@' . meloverse_h((string) $c['username']) . '</strong> ';
        echo '<span class="mv-muted">' . meloverse_h(date('M j, Y g:i A', strtotime((string) $c['created_at']))) . '</span></div>';
        echo '<div class="mv-comment__body">' . nl2br(meloverse_h((string) $c['body'])) . '</div>';
        if (meloverse_current_user()) {
            echo '<button type="button" class="mv-linkbtn" data-reply-to="' . $cid . '">Reply</button>';
        }
        meloverse_render_comments($byParent, $cid, $depth + 1);
        echo '</li>';
    }
    echo '</ul>';
}

$pageTitle = (string) $post['title'];
require __DIR__ . '/partials/header.php';
?>
<div class="mv-feed">
    <?php require __DIR__ . '/partials/post_card.php'; ?>
    <section class="mv-comments mv-hover-glow" id="comments">
        <h2>Comments</h2>
        <?php if ($currentUser): ?>
            <form class="mv-form mv-comment-form" id="mv-comment-form" data-post="<?= (int) $post['id'] ?>">
                <input type="hidden" name="parent_id" id="mv-parent-id" value="">
                <p class="mv-muted" id="mv-reply-hint" hidden>Replying to comment… <button type="button" class="mv-linkbtn" id="mv-cancel-reply">Cancel</button></p>
                <label>
                    <textarea name="body" rows="3" maxlength="4000" required placeholder="Write a comment…"></textarea>
                </label>
                <button type="submit" class="mv-btn">Post comment</button>
            </form>
        <?php else: ?>
            <p class="mv-muted"><a href="login.php">Log in</a> to comment.</p>
        <?php endif; ?>
        <div id="mv-comment-tree">
            <?php if (!$flat): ?>
                <p class="mv-muted">No comments yet.</p>
            <?php else: ?>
                <?php meloverse_render_comments($byParent, 0); ?>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
