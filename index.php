<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/feed.php';

$currentUser = meloverse_current_user();
meloverse_guest_mobile_redirect();

$tab = $_GET['tab'] ?? 'latest';
$tab = $tab === 'trending' ? 'trending' : 'latest';
$viewerId = $currentUser ? (int) $currentUser['id'] : 0;
$posts = meloverse_fetch_posts($tab, $viewerId, 40);

$pageTitle = 'Home';
require __DIR__ . '/partials/header.php';
?>
<div class="mv-feed-layout">
    <section class="mv-feed">
        <div class="mv-tabs">
            <a class="mv-tab<?= $tab === 'latest' ? ' is-active' : '' ?>" href="index.php?tab=latest">Latest</a>
            <a class="mv-tab<?= $tab === 'trending' ? ' is-active' : '' ?>" href="index.php?tab=trending">Trending</a>
        </div>
        <?php if (!$posts): ?>
            <p class="mv-empty">No tracks yet. Be the first to <a href="upload.php">upload</a>.</p>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <?php require __DIR__ . '/partials/post_card.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
    <aside class="mv-sidebar mv-hover-glow">
        <h3>Discover</h3>
        <p class="mv-muted">Search people and tracks, follow artists, and save what you love.</p>
        <a class="mv-btn mv-btn--block" href="search.php">Search</a>
        <?php if ($currentUser): ?>
            <a class="mv-btn mv-btn--block mv-btn--secondary" href="upload.php">Upload audio</a>
        <?php endif; ?>
    </aside>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
