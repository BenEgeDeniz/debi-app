<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/feed/feed_service.php';

$currentUser = require_login();
$post = get_single_feed_post((int) ($_GET['id'] ?? 0), $currentUser);
if (!$post) {
    set_flash('error', 'Bu paylaşım bulunamadı veya görüntüleme yetkiniz yok.');
    redirect('index.php');
}

if (!empty($_GET['notification'])) {
    $readStmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid');
    $readStmt->execute([
        ':id' => (int) $_GET['notification'],
        ':uid' => (int) $currentUser['id'],
    ]);
}

$pageTitle = 'Paylaşım — Debi';
$activeNav = 'feed';
require_once __DIR__ . '/includes/header.php';
?>
<div class="app-shell app-shell-2col">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>
    <main class="main-column">
        <div class="page-back-row"><a href="index.php" class="btn btn-outline btn-sm">← Akışa dön</a></div>
        <article class="panel-card post-card post-single" id="post-<?= (int) $post['id'] ?>">
            <div class="post-header">
                <div class="post-author-group">
                    <a href="profile.php?id=<?= (int) $post['user_id'] ?>"><?= render_avatar($post) ?></a>
                    <div>
                        <div class="post-author-line">
                            <a href="profile.php?id=<?= (int) $post['user_id'] ?>" class="author-link">
                                <strong><?= e($post['full_name']) ?> · @<?= e($post['nickname'] ?? '') ?></strong>
                            </a>
                            <?php if ($post['user_role'] === 'manager'): ?><span class="tag tag-manager">Yönetici</span><?php endif; ?>
                        </div>
                        <div class="post-location-line"><?= e(time_ago($post['created_at'])) ?></div>
                    </div>
                </div>
            </div>
            <div class="post-body">
                <p><?= render_mention_content($post['content']) ?></p>
                <?php if (!empty($post['image_path'])): ?>
                    <div class="post-media-frame"><img src="<?= e($post['image_path']) ?>" alt="Paylaşım görseli"></div>
                <?php endif; ?>
            </div>
            <div class="post-footer">
                <span class="btn-action">💧 <?= (int) $post['reaction_count'] ?></span>
                <span class="btn-action">💬 <?= (int) $post['comment_count'] ?></span>
            </div>
            <?php if (!empty($post['comments'])): ?>
                <div class="comments-box">
                    <ul class="comment-list">
                        <?php foreach ($post['comments'] as $comment): ?>
                            <li class="comment-item">
                                <a href="profile.php?id=<?= (int) $comment['user_id'] ?>"><?= render_avatar($comment, 'avatar-xs') ?></a>
                                <div class="comment-content">
                                    <div class="comment-meta">
                                        <a href="profile.php?id=<?= (int) $comment['user_id'] ?>" class="author-link"><strong><?= e($comment['full_name']) ?> · @<?= e($comment['nickname'] ?? '') ?></strong></a>
                                        <time><?= e(time_ago($comment['created_at'])) ?></time>
                                    </div>
                                    <p><?= render_mention_content($comment['content']) ?></p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </article>
    </main>
    <?php require_once __DIR__ . '/includes/sidebar_right.php'; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
