<?php
/**
 * DEBI - Sosyal Medya Akış Modülü (Gönderi, Damla Tepkisi, Yorum)
 */

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
    $params = [':current_uid' => (int) $user['id']];
    $where  = [];

    if ($scope === 'apartment') {
        if (empty($user['apartment_id'])) {
            return [];
        }
        $where[]        = "p.apartment_id = :aid";
        $params[':aid'] = (int) $user['apartment_id'];
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
        ':author_uid'  => $profileId,
    ];

    if ($profileId === $viewerId) {
        $whereSql = "p.user_id = :author_uid";
    } else {
        $allowedVis = ["'public'", "'neighborhood'"];
        if (function_exists('get_friendship_relation')) {
            $rel = get_friendship_relation($viewerId, $profileId);
            if (($rel['state'] ?? '') === 'accepted') {
                $allowedVis[] = "'friends'";
            }
        }
        if (!empty($viewerUser['apartment_id']) && (int) $viewerUser['apartment_id'] === (int) ($profileUser['apartment_id'] ?? 0)) {
            $allowedVis[] = "'apartment'";
        }
        $visList  = implode(',', $allowedVis);
        $whereSql = "p.user_id = :author_uid AND p.visibility IN ({$visList})";
    }

    return fetch_posts_with_comments($pdo, $whereSql, $params);
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
            u.role AS user_role,
            u.avatar_color,
            u.total_points AS user_points,
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
            u.avatar_color,
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
        $post['comments'] = $commentsByPost[(int) $post['id']] ?? [];
    }
    unset($post);

    return $posts;
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
function add_post_comment(int $postId, int $userId, string $content): array
{
    $content = trim($content);
    if (mb_strlen($content, 'UTF-8') < 2) {
        return ['ok' => false, 'error' => 'Yorum metni boş olamaz.'];
    }

    $pdo  = db();
    $stmt = $pdo->prepare("
        INSERT INTO comments (post_id, user_id, content)
        VALUES (:pid, :uid, :content)
    ");
    $stmt->execute([
        ':pid'     => $postId,
        ':uid'     => $userId,
        ':content' => $content,
    ]);
    $commentId = (int) $pdo->lastInsertId();

    $pts = 0;
    if (function_exists('award_points')) {
        $uSt = $pdo->prepare("SELECT household_id FROM users WHERE id = :uid LIMIT 1");
        $uSt->execute([':uid' => $userId]);
        $hid = (int) $uSt->fetchColumn();
        if ($hid > 0) {
            $pts = award_points($userId, $hid, 'comment_create', 10, '💬 Komşunun paylaşımına yorum yaptın!', 'comment_' . $commentId);
        }
    }

    return ['ok' => true, 'points_awarded' => $pts];
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

