<?php
/**
 * DEBI - Sosyal Medya Akış Modülü (Gönderi, Damla Tepkisi, Yorum)
 */
require_once __DIR__ . '/debbie_ai_service.php';

/**
 * Yeni Gönderi Oluşturma (Opsiyonel Görsel Yükleme Desteği ile)
 */
function create_post(array $user, array $input, ?array $file = null): array
{
    $content          = trim($input['content'] ?? '');
    $category         = trim($input['category'] ?? 'tip');
    $visibility       = trim($input['visibility'] ?? 'neighborhood');
    $waterSavedLiters = !empty($input['water_saved_liters']) ? max(1, (int) $input['water_saved_liters']) : null;
    $isPinned         = (!empty($input['is_pinned']) && $user['role'] === 'manager') ? 1 : 0;
    $imagePath        = null;

    if (mb_strlen($content, 'UTF-8') < 4) {
        return ['ok' => false, 'error' => 'Paylaşım metni çok kısa.'];
    }

    $allowedCategories = ['tip', 'achievement', 'question', 'alert', 'announcement'];
    if (!in_array($category, $allowedCategories, true)) {
        $category = 'tip';
    }

    if ($category === 'announcement' && $user['role'] !== 'manager') {
        $category = 'tip';
    }

    $allowedVisibilities = ['apartment', 'friends', 'public'];
    if (!in_array($visibility, $allowedVisibilities, true)) {
        $visibility = 'public';
    }

    // Apartmanı olmayan kullanıcı apartman içi paylaşım seçemez -> arkadaşlara düşer
    if ($visibility === 'apartment' && empty($user['apartment_id'])) {
        $visibility = 'friends';
    }

    // Görsel yükleme kontrolü
    if ($file && !empty($file['tmp_name']) && $file['error'] === UPLOAD_ERR_OK) {
        $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset($allowedMimes[$mime])) {
            return ['ok' => false, 'error' => 'Sadece JPG, PNG veya WebP formatında görsel yükleyebilirsiniz.'];
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'Görsel boyutu en fazla 5MB olabilir.'];
        }

        $uploadDir = __DIR__ . '/../../uploads/posts';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName  = 'post_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $allowedMimes[$mime];
        $target    = $uploadDir . '/' . $fileName;

        if (move_uploaded_file($file['tmp_name'], $target)) {
            $imagePath = 'uploads/posts/' . $fileName;
        }
    }

    $stmt = db()->prepare("
        INSERT INTO posts (
            user_id, neighborhood_id, apartment_id, visibility,
            category, content, water_saved_liters, image_path, is_pinned
        ) VALUES (
            :uid, :nid, :aid, :vis,
            :cat, :content, :saved, :img, :pinned
        )
    ");
    $stmt->execute([
        ':uid'     => (int) $user['id'],
        ':nid'     => (int) $user['neighborhood_id'],
        ':aid'     => !empty($user['apartment_id']) ? (int) $user['apartment_id'] : null,
        ':vis'     => $visibility,
        ':cat'     => $category,
        ':content' => $content,
        ':saved'   => $waterSavedLiters,
        ':img'     => $imagePath,
        ':pinned'  => $isPinned,
    ]);

    $postId = (int) db()->lastInsertId();
    create_mention_notifications($user, $postId, null, mentioned_user_ids_from_content($content), $visibility);
    maybe_answer_as_debbie($user, $postId, null, $content);
    $pts = 0;
    if (function_exists('award_points')) {
        $pts = award_points(
            (int) $user['id'],
            (int) $user['household_id'],
            'post_create',
            25,
            '📣 Akışta yeni bir su tasarrufu paylaşımı yaptın!',
            'post_' . $postId
        );
    }

    return ['ok' => true, 'post_id' => $postId, 'points_awarded' => $pts];
}

/**
 * Akış Gönderilerini Listeleme
 * $scope: 'friends' (Arkadaşlar), 'apartment' (Apartman), 'public' (Genel Debi)
 */
function get_feed_posts(array $user, string $scope = 'friends', ?string $categoryFilter = null): array
{
    $pdo    = db();
    $params = [
        ':current_uid' => (int) $user['id'],
        ':mention_access_uid' => (int) $user['id'],
    ];
    $where  = [];

    if ($scope === 'apartment') {
        if (empty($user['apartment_id'])) {
            $where[] = '1 = 0';
        } else {
            $where[]        = "p.apartment_id = :aid";
            $params[':aid'] = (int) $user['apartment_id'];
        }
    } elseif ($scope === 'friends') {
        $friendIds   = function_exists('get_user_friend_ids') ? get_user_friend_ids((int) $user['id']) : [];
        $authorIds   = array_values(array_unique(array_merge([(int) $user['id']], $friendIds)));
        $inParams    = [];
        foreach ($authorIds as $idx => $fid) {
            $key            = ':fid_' . $idx;
            $inParams[]     = $key;
            $params[$key]   = (int) $fid;
        }
        $inSql   = implode(',', $inParams);
        $where[] = "(p.user_id IN ({$inSql}) AND p.visibility IN ('friends', 'public', 'neighborhood'))";
    } else {
        // 'public' (Genel Debi)
        $where[] = "p.visibility IN ('public', 'neighborhood')";
    }

    if ($categoryFilter && in_array($categoryFilter, ['tip', 'achievement', 'question', 'alert', 'announcement'], true)) {
        $where[]        = "p.category = :cat";
        $params[':cat'] = $categoryFilter;
    }

    $whereSql = implode(' AND ', $where);
    $whereSql = "({$whereSql} OR EXISTS (
        SELECT 1 FROM notifications mention_access
        WHERE mention_access.post_id = p.id
          AND mention_access.user_id = :mention_access_uid
          AND mention_access.type = 'mention'
    ))";

    return fetch_posts_with_comments($pdo, $whereSql, $params);
}

/**
 * Kullanıcının Profil "Zaman Tüneli" (Timeline) Gönderilerini Listeleme
 */
function get_user_timeline_posts(array $profileUser, array $viewerUser): array
{
    $pdo       = db();
    $profileId = (int) $profileUser['id'];
    $viewerId  = (int) $viewerUser['id'];

    $params = [
        ':current_uid' => $viewerId,
        ':author_uid' => $profileId,
    ];
    $whereSql = "p.user_id = :author_uid";

    return fetch_posts_with_comments($pdo, $whereSql, $params);
}

function get_single_feed_post(int $postId, array $user): ?array
{
    if ($postId <= 0) {
        return null;
    }

    $pdo = db();
    $params = [
        ':current_uid' => (int) $user['id'],
        ':viewer_uid' => (int) $user['id'],
        ':mention_access_uid' => (int) $user['id'],
        ':post_id' => $postId,
    ];
    $allowed = ["'public'", "'neighborhood'"];
    if (!empty($user['apartment_id'])) {
        $allowed[] = "'apartment'";
    }
    if (function_exists('get_user_friend_ids')) {
        $friendIds = get_user_friend_ids((int) $user['id']);
        if ($friendIds) {
            $friendKeys = [];
            foreach (array_values($friendIds) as $index => $friendId) {
                $key = ':single_friend_' . $index;
                $friendKeys[] = $key;
                $params[$key] = (int) $friendId;
            }
            $friendSql = implode(',', $friendKeys);
        }
    }

    $where = "p.id = :post_id AND (p.user_id = :viewer_uid OR p.visibility IN (" . implode(',', $allowed) . ")";
    if (!empty($friendSql)) {
        $where .= " OR (p.user_id IN ({$friendSql}) AND p.visibility = 'friends')";
    }
    $where .= " OR EXISTS (
        SELECT 1 FROM notifications mention_access
        WHERE mention_access.post_id = p.id
          AND mention_access.user_id = :mention_access_uid
          AND mention_access.type = 'mention'
    )";
    $where .= ')';

    $posts = fetch_posts_with_comments($pdo, $where, $params);
    return $posts[0] ?? null;
}

/**
 * Ortak Gönderi ve Yorum Sorgulayıcı
 */
function fetch_posts_with_comments(PDO $pdo, string $whereSql, array $params): array
{
    $sql = "
        SELECT
            p.*,
            u.full_name,
            u.nickname,
            u.role AS user_role,
            u.avatar_color,
            u.avatar_photo,
            h.total_points AS user_points,
            h.housing_type,
            h.unit_label,
            n.city AS post_city,
            n.district AS post_district,
            n.name AS post_neighborhood,
            a.name AS post_apartment,
            (SELECT COUNT(*) FROM post_reactions pr WHERE pr.post_id = p.id) AS reaction_count,
            (SELECT COUNT(*) FROM post_reactions pr2 WHERE pr2.post_id = p.id AND pr2.user_id = :current_uid) AS user_reacted,
            (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count
        FROM posts p
        INNER JOIN users u ON u.id = p.user_id
        INNER JOIN households h ON h.id = u.household_id
        INNER JOIN neighborhoods n ON n.id = p.neighborhood_id
        LEFT JOIN apartments a ON a.id = p.apartment_id
        WHERE {$whereSql}
        ORDER BY p.is_pinned DESC, p.created_at DESC
        LIMIT 40
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();

    if (!$posts) {
        return [];
    }

    // Gönderilerin yorumlarını tek sorguda çek
    $postIds      = array_map(fn($r) => (int) $r['id'], $posts);
    $placeholders = implode(',', array_fill(0, count($postIds), '?'));

    $cStmt = $pdo->prepare("
        SELECT
            c.*,
            u.full_name,
            u.nickname,
            u.avatar_color,
            u.avatar_photo,
            h.unit_label,
            a.name AS apartment_name
        FROM comments c
        INNER JOIN users u ON u.id = c.user_id
        INNER JOIN households h ON h.id = u.household_id
        LEFT JOIN apartments a ON a.id = u.apartment_id
        WHERE c.post_id IN ({$placeholders})
        ORDER BY c.created_at ASC
    ");
    $cStmt->execute($postIds);
    $allComments = $cStmt->fetchAll();

    $commentsByPost = [];
    foreach ($allComments as $comment) {
        $commentsByPost[(int) $comment['post_id']][] = $comment;
    }

    foreach ($posts as &$post) {
        $post['comments'] = add_comment_depths($commentsByPost[(int) $post['id']] ?? []);
    }

    unset($post);

    return $posts;
}

function add_comment_depths(array $comments): array
{
    $byParent = [];
    foreach ($comments as $comment) {
        $parentId = (int) ($comment['parent_comment_id'] ?? 0);
        $byParent[$parentId][] = $comment;
    }

    $result = [];
    $visit = function (int $parentId, int $depth) use (&$visit, &$result, $byParent): void {
        foreach ($byParent[$parentId] ?? [] as $comment) {
            $comment['depth'] = min($depth, 8);
            $result[] = $comment;
            $visit((int) $comment['id'], $depth + 1);
        }
    };
    $visit(0, 0);
    return $result;
}

/**
 * Gönderiye "Damla" Desteği Verme / Geri Çekme
 */
function toggle_post_reaction(int $postId, int $userId): array
{
    $pdo = db();

    $chk = $pdo->prepare("SELECT id FROM post_reactions WHERE post_id = :pid AND user_id = :uid LIMIT 1");
    $chk->execute([':pid' => $postId, ':uid' => $userId]);
    $existingId = $chk->fetchColumn();

    if ($existingId) {
        $del = $pdo->prepare("DELETE FROM post_reactions WHERE id = :id");
        $del->execute([':id' => $existingId]);
        $reacted = false;
    } else {
        $ins = $pdo->prepare("INSERT INTO post_reactions (post_id, user_id) VALUES (:pid, :uid)");
        $ins->execute([':pid' => $postId, ':uid' => $userId]);
        $reacted = true;

        if (function_exists('award_points')) {
            // Tepki veren kullanıcının household_id'sini al
            $uSt = $pdo->prepare("SELECT household_id FROM users WHERE id = :uid LIMIT 1");
            $uSt->execute([':uid' => $userId]);
            $reactorHid = (int) $uSt->fetchColumn();
            if ($reactorHid > 0) {
                award_points($userId, $reactorHid, 'post_reaction_give', 5, '💧 Komşunun paylaşımına damla desteği verdin!', 'react_give_' . $postId);
            }

            // Gönderi sahibine de +10 DP kazandır (kendi gönderisi değilse)
            $pSt = $pdo->prepare("SELECT p.user_id, u.household_id FROM posts p INNER JOIN users u ON u.id = p.user_id WHERE p.id = :pid LIMIT 1");
            $pSt->execute([':pid' => $postId]);
            $author = $pSt->fetch();
            if ($author && (int) $author['user_id'] !== $userId) {
                award_points(
                    (int) $author['user_id'],
                    (int) $author['household_id'],
                    'post_reaction_receive',
                    10,
                    '💙 Paylaşımın komşulardan damla desteği aldı!',
                    'react_recv_' . $postId . '_' . $userId
                );
            }
        }
    }

    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM post_reactions WHERE post_id = :pid");
    $cntStmt->execute([':pid' => $postId]);
    $count = (int) $cntStmt->fetchColumn();

    return ['ok' => true, 'reacted' => $reacted, 'count' => $count];
}

/**
 * Gönderiye Yorum Ekleme
 */
function add_post_comment(array $user, int $postId, string $content, array $taggedUserIds = [], int $parentCommentId = 0, bool $awardPoints = true): array
{
    $content = trim($content);
    if (mb_strlen($content, 'UTF-8') < 2) {
        return ['ok' => false, 'error' => 'Yorum metni boş olamaz.'];
    }

    $pdo  = db();
    $parentCommentId = max(0, $parentCommentId);
    if ($parentCommentId > 0) {
        $parentUserStmt = $pdo->prepare("
            SELECT u.nickname
            FROM comments c
            INNER JOIN users u ON u.id = c.user_id
            WHERE c.id = :comment_id AND c.post_id = :post_id
            LIMIT 1
        ");
        $parentUserStmt->execute([
            ':comment_id' => $parentCommentId,
            ':post_id' => $postId,
        ]);
        $parentNickname = $parentUserStmt->fetchColumn();
        if ($parentNickname) {
            $requiredMention = '@' . $parentNickname . ';';
            if (!preg_match('/^@' . preg_quote((string) $parentNickname, '/') . '(?:;|\s)/i', $content)) {
                $content = $requiredMention . ' ' . $content;
            }
        }
    }
    $stmt = $pdo->prepare("
        INSERT INTO comments (post_id, parent_comment_id, user_id, content)
        SELECT :pid, CASE WHEN :parent_id > 0 AND EXISTS (
            SELECT 1 FROM comments parent WHERE parent.id = :parent_id_check AND parent.post_id = :parent_post
        ) THEN :parent_id_value ELSE NULL END, :uid, :content
    ");
    $stmt->execute([
        ':pid'     => $postId,
        ':parent_id' => $parentCommentId,
        ':parent_id_check' => $parentCommentId,
        ':parent_post' => $postId,
        ':parent_id_value' => $parentCommentId,
        ':uid'     => (int) $user['id'],
        ':content' => $content,
    ]);
    $commentId = (int) $pdo->lastInsertId();
    $postStmt = $pdo->prepare("SELECT visibility FROM posts WHERE id = :id LIMIT 1");
    $postStmt->execute([':id' => $postId]);
    create_mention_notifications($user, $postId, $commentId, mentioned_user_ids_from_content($content), (string) $postStmt->fetchColumn());
    maybe_answer_as_debbie($user, $postId, $commentId, $content);

    $pts = 0;
    if ($awardPoints && function_exists('award_points')) {
        $uSt = $pdo->prepare("SELECT household_id FROM users WHERE id = :uid LIMIT 1");
        $uSt->execute([':uid' => (int) $user['id']]);
        $hid = (int) $uSt->fetchColumn();
        if ($hid > 0) {
            $pts = award_points((int) $user['id'], $hid, 'comment_create', 10, '💬 Komşunun paylaşımına yorum yaptın!', 'comment_' . $commentId);
        }

    }

    return [
        'ok' => true,
        'comment_id' => $commentId,
        'parent_comment_id' => $parentCommentId,
        'content' => $content,
        'user_id' => (int) $user['id'],
        'full_name' => (string) ($user['full_name'] ?? ''),
        'nickname' => (string) ($user['nickname'] ?? ''),
        'points_awarded' => $pts,
    ];
}

function get_taggable_users(array $user, string $visibility): array
{
    $params = [':uid' => (int) $user['id']];
    if ($visibility === 'apartment' && !empty($user['apartment_id'])) {
        $where = 'u.apartment_id = :aid';
        $params[':aid'] = (int) $user['apartment_id'];
    } elseif ($visibility === 'friends') {
        $ids = function_exists('get_user_friend_ids') ? get_user_friend_ids((int) $user['id']) : [];
        if (!$ids) return [];
        $keys = [];
        foreach (array_values($ids) as $i => $id) {
            $key = ':friend_' . $i;
            $keys[] = $key;
            $params[$key] = (int) $id;
        }
        $where = 'u.id IN (' . implode(',', $keys) . ')';
    } else {
        $where = 'u.neighborhood_id = :nid';
        $params[':nid'] = (int) $user['neighborhood_id'];
    }
    $stmt = db()->prepare("SELECT u.id, u.full_name, u.nickname FROM users u WHERE u.id != :uid AND (u.is_ai_bot = 0 OR u.is_ai_bot IS NULL) AND {$where} ORDER BY u.full_name LIMIT 100");
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    $botStmt = db()->query("SELECT id, full_name, nickname FROM users WHERE nickname = 'debbie' AND is_ai_bot = 1 LIMIT 1");
    $bot = $botStmt->fetch();
    if ($bot) {
        array_unshift($users, $bot);
    }
    return $users;
}

function create_mention_notifications(array $actor, int $postId, ?int $commentId, array $taggedUserIds, string $visibility): void
{
    if (!$taggedUserIds) return;
    try {
        $insert = db()->prepare("INSERT INTO notifications (user_id, actor_user_id, type, post_id, comment_id, message) VALUES (:uid, :actor, 'mention', :post, :comment, :message)");
        foreach (array_unique(array_map('intval', $taggedUserIds)) as $userId) {
            if ($userId <= 0 || $userId === (int) $actor['id']) continue;
            $userExists = db()->prepare('SELECT 1 FROM users WHERE id = :uid LIMIT 1');
            $userExists->execute([':uid' => $userId]);
            if (!$userExists->fetchColumn()) continue;
            $insert->execute([
                ':uid' => $userId, ':actor' => (int) $actor['id'], ':post' => $postId ?: null,
                ':comment' => $commentId, ':message' => $commentId ? 'Bir yorumda etiketlendin.' : 'Bir paylaşımda etiketlendin.',
            ]);
        }
    } catch (PDOException $exception) {
        error_log('Mention notification creation failed: ' . $exception->getMessage());
    }
}

function mentioned_user_ids_from_content(string $content): array
{
    preg_match_all('/@([a-z0-9_]{3,32})(?:;|\\b)/i', $content, $matches);
    if (empty($matches[1])) return [];
    $nicknames = array_values(array_unique(array_map(static fn ($value) => mb_strtolower($value, 'UTF-8'), $matches[1])));
    $placeholders = [];
    $params = [];
    foreach ($nicknames as $index => $nickname) {
        $key = ':nickname_' . $index;
        $placeholders[] = $key;
        $params[$key] = $nickname;
    }
    try {
        $stmt = db()->prepare('SELECT id FROM users WHERE nickname IN (' . implode(',', $placeholders) . ')');
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (PDOException $exception) {
        error_log('Mention lookup failed: ' . $exception->getMessage());
        return [];
    }
}

function render_mention_content(?string $content): string
{
    $content = (string) ($content ?? '');
    $parts = preg_split('/(@[a-z0-9_]{3,32}(?:;|\\b))/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return nl2br(e($content));
    }
    $html = '';
    foreach ($parts as $part) {
        if (preg_match('/^@([a-z0-9_]{3,32})(?:;|\\b)$/i', $part, $match)) {
            $user = false;
            try {
                $stmt = db()->prepare('SELECT id, full_name, nickname FROM users WHERE nickname = :nickname LIMIT 1');
                $stmt->execute([':nickname' => mb_strtolower($match[1], 'UTF-8')]);
                $user = $stmt->fetch();
            } catch (PDOException $exception) {
                error_log('Mention rendering failed: ' . $exception->getMessage());
            }
            if ($user) {
                $suffix = str_ends_with($part, ';') ? ';' : '';
                $html .= '<a class="mention-link" href="profile.php?id=' . (int) $user['id']
                    . '" title="' . e($user['full_name']) . '"><strong>@'
                    . e($user['nickname']) . $suffix . '</strong></a>';
                continue;
            }
        }
        $html .= nl2br(e($part));
    }
    return $html;
}

function count_unread_notifications(int $userId): int
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
    $stmt->execute([':uid' => $userId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Gönderi Silme (Gönderi sahibi veya aynı apartmanın yöneticisi)
 */
function delete_post(int $postId, array $user): bool
{
    $pdo  = db();
    $stmt = $pdo->prepare("SELECT user_id, apartment_id FROM posts WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $postId]);
    $post = $stmt->fetch();

    if (!$post) {
        return false;
    }

    $canDelete = ((int) $post['user_id'] === (int) $user['id']) ||
        ($user['role'] === 'manager' && !empty($user['apartment_id']) && (int) $post['apartment_id'] === (int) $user['apartment_id']);

    if (!$canDelete) {
        return false;
    }

    $del = $pdo->prepare("DELETE FROM posts WHERE id = :id");
    $del->execute([':id' => $postId]);
    return true;
}
