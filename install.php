<?php
declare(strict_types=1);

/**
 * One-time installer: creates tables and the admin account.
 * Remove or protect this file in production after setup.
 */
header('Content-Type: text/html; charset=utf-8');

$configPath = __DIR__ . '/config.php';
if (!is_readable($configPath)) {
    echo '<p>Copy <code>config.example.php</code> to <code>config.php</code> and set database credentials first.</p>';
    exit;
}

/** @var array<string,mixed> $config */
$config = require $configPath;
$db = $config['db'];

$dsn = sprintf(
    'mysql:host=%s;port=%d;charset=%s',
    $db['host'],
    (int) $db['port'],
    $db['charset']
);

try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $db['name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . str_replace('`', '``', $db['name']) . '`');

    $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  username VARCHAR(64) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(120) NOT NULL DEFAULT '',
  bio TEXT NULL,
  avatar_path VARCHAR(512) NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  is_banned TINYINT(1) NOT NULL DEFAULT 0,
  followers_count INT UNSIGNED NOT NULL DEFAULT 0,
  following_count INT UNSIGNED NOT NULL DEFAULT 0,
  posts_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_username (username),
  KEY idx_users_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audio_posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  audio_path VARCHAR(512) NOT NULL,
  audio_filename VARCHAR(255) NOT NULL,
  mime_type VARCHAR(64) NULL,
  duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  plays_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_posts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_posts_user_created (user_id, created_at),
  KEY idx_posts_created (created_at),
  KEY idx_posts_trending (plays_count, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hashtags (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tag VARCHAR(100) NOT NULL,
  UNIQUE KEY uq_hashtags_tag (tag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_hashtags (
  post_id INT UNSIGNED NOT NULL,
  hashtag_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (post_id, hashtag_id),
  CONSTRAINT fk_ph_post FOREIGN KEY (post_id) REFERENCES audio_posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_ph_tag FOREIGN KEY (hashtag_id) REFERENCES hashtags(id) ON DELETE CASCADE,
  KEY idx_ph_hashtag (hashtag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS likes (
  user_id INT UNSIGNED NOT NULL,
  post_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, post_id),
  CONSTRAINT fk_likes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_likes_post FOREIGN KEY (post_id) REFERENCES audio_posts(id) ON DELETE CASCADE,
  KEY idx_likes_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  parent_id INT UNSIGNED NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_comments_post FOREIGN KEY (post_id) REFERENCES audio_posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_comments_parent FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
  KEY idx_comments_post_parent (post_id, parent_id),
  KEY idx_comments_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS follows (
  follower_id INT UNSIGNED NOT NULL,
  following_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (follower_id, following_id),
  CONSTRAINT fk_follows_follower FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_follows_following FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_follows_following (following_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  actor_id INT UNSIGNED NOT NULL,
  type ENUM('like','comment','follow','mention') NOT NULL,
  post_id INT UNSIGNED NULL,
  comment_id INT UNSIGNED NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notif_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notif_post FOREIGN KEY (post_id) REFERENCES audio_posts(id) ON DELETE CASCADE,
  KEY idx_notif_user_read_created (user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookmarks (
  user_id INT UNSIGNED NOT NULL,
  post_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, post_id),
  CONSTRAINT fk_bm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_bm_post FOREIGN KEY (post_id) REFERENCES audio_posts(id) ON DELETE CASCADE,
  KEY idx_bookmarks_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt !== '') {
            $pdo->exec($stmt);
        }
    }

    $adminEmail = 'admin@meloverse.com';
    $adminPass = 'Admin@2024';
    $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $check->execute([$adminEmail]);
    if (!$check->fetch()) {
        $hash = password_hash($adminPass, PASSWORD_DEFAULT);
        $ins = $pdo->prepare(
            'INSERT INTO users (email, username, password_hash, display_name, role)
             VALUES (?, ?, ?, ?, ?)'
        );
        $ins->execute([$adminEmail, 'admin', $hash, 'Meloverse Admin', 'admin']);
        echo '<p>Admin user created: <strong>' . htmlspecialchars($adminEmail) . '</strong> / password as specified in your prompt.</p>';
    } else {
        echo '<p>Admin user already exists (skipped).</p>';
    }

    echo '<p>Database <strong>' . htmlspecialchars($db['name']) . '</strong> is ready. <a href="index.php">Open MELOVERSE</a></p>';
    echo '<p><strong>Security:</strong> delete or restrict access to <code>install.php</code> after setup.</p>';
} catch (Throwable $e) {
    http_response_code(500);
    echo '<p>Install failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
