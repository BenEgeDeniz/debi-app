<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/feed/feed_service.php';

$currentUser = require_login();
$postId = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Güvenlik doğrulaması başarısız oldu.');
        redirect('post.php?id=' . $postId);
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'toggle_reaction') {
        $result = toggle_post_reaction($postId, (int) $currentUser['id']);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($result);
            exit;
        }
    } elseif ($action === 'add_comment') {
        $result = add_post_comment(
            $currentUser,
            $postId,
            (string) ($_POST['comment_content'] ?? ''),
            (array) ($_POST['tagged_user_ids'] ?? []),
            (int) ($_POST['parent_comment_id'] ?? 0)
        );
        set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Yorumunuz eklendi.' : $result['error']);
    }
    redirect('post.php?id=' . $postId);
}

$post = get_single_feed_post($postId, $currentUser);
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
$taggableUsers = array_values(get_taggable_users($currentUser, $post['visibility']));
require_once __DIR__ . '/includes/header.php';
?>
<div class="app-shell">
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
                <form method="post" action="post.php?id=<?= (int) $post['id'] ?>" class="reaction-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_reaction">
                    <button type="submit" class="btn-action <?= (int) $post['user_reacted'] > 0 ? 'reacted' : '' ?>">
                        <span class="drop-symbol">💧</span>
                        <span class="reaction-count"><?= (int) $post['reaction_count'] ?></span>
                    </button>
                </form>
                <span class="btn-action">💬 <?= (int) $post['comment_count'] ?></span>
            </div>
            <div class="comments-box">
                <?php if (!empty($post['comments'])): ?>
                    <ul class="comment-list">
                        <?php foreach ($post['comments'] as $comment): ?>
                            <li class="comment-item comment-depth-<?= (int) ($comment['depth'] ?? 0) ?>"
                                data-comment-id="<?= (int) $comment['id'] ?>"
                                data-parent-comment-id="<?= (int) ($comment['parent_comment_id'] ?? 0) ?>">
                                <a href="profile.php?id=<?= (int) $comment['user_id'] ?>"><?= render_avatar($comment, 'avatar-xs') ?></a>
                                <div class="comment-content">
                                    <div class="comment-meta">
                                        <a href="profile.php?id=<?= (int) $comment['user_id'] ?>" class="author-link"><strong><?= e($comment['full_name']) ?> · @<?= e($comment['nickname'] ?? '') ?></strong></a>
                                        <time><?= e(time_ago($comment['created_at'])) ?></time>
                                    </div>
                                    <p><?= render_mention_content($comment['content']) ?></p>
                                    <button type="button" class="comment-reply-btn" data-comment-id="<?= (int) $comment['id'] ?>" data-reply-nickname="<?= e($comment['nickname'] ?? '') ?>">Yanıtla</button>
                                    <form method="post" action="post.php?id=<?= (int) $post['id'] ?>" class="comment-form comment-reply-form" hidden>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="add_comment">
                                        <input type="hidden" name="parent_comment_id" value="<?= (int) $comment['id'] ?>">
                                        <div class="mention-field" data-mention-users="<?= e(json_encode($taggableUsers, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
                                            <input type="text" name="comment_content" autocomplete="off" required placeholder="Yanıt yazın...">
                                            <div class="mention-suggestions" hidden></div>
                                        </div>
                                        <button type="submit" class="btn btn-outline btn-sm">Yanıtla</button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <form method="post" action="post.php?id=<?= (int) $post['id'] ?>" class="comment-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_comment">
                    <input type="hidden" name="parent_comment_id" value="0">
                    <div class="mention-field" data-mention-users="<?= e(json_encode($taggableUsers, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
                        <input type="text" name="comment_content" autocomplete="off" required placeholder="Yorum yazın...">
                        <div class="mention-suggestions" hidden></div>
                    </div>
                    <button type="submit" class="btn btn-outline btn-sm">Gönder</button>
                </form>
            </div>
        </article>
    </main>
    <?php require_once __DIR__ . '/includes/sidebar_right.php'; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
