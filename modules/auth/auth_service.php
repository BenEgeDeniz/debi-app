<?php
/**
 * DEBI - Kimlik Doğrulama ve Kayıt Modülü
 * Modlar:
 * 1) individual        -> Bireysel Daire veya Müstakil Ev olarak doğrudan Mahalleye katılım
 * 2) apartment_invite  -> Davet kodu ile mevcut Apartmana (ve onun Mahallesine) katılım
 * 3) apartment_manager -> Mahallede yeni Apartman oluşturup yönetici olarak katılım
 */

/**
 * İl / İlçe / Mahalle kaydını bulur, yoksa oluşturur ve ID döndürür.
 */
function find_or_create_neighborhood(PDO $pdo, string $city, string $district, string $neighborhoodName): int
{
    $city             = normalize_place_name($city);
    $district         = normalize_place_name($district);
    $neighborhoodName = normalize_place_name($neighborhoodName);

    if (! str_ends_with(mb_strtolower($neighborhoodName, 'UTF-8'), 'mah.') &&
        ! str_ends_with(mb_strtolower($neighborhoodName, 'UTF-8'), 'mahallesi')) {
        $neighborhoodName .= ' Mah.';
    }

    $stmt = $pdo->prepare("
        SELECT id FROM neighborhoods
        WHERE city = :city AND district = :district AND name = :name
        LIMIT 1
    ");
    $stmt->execute([
        ':city'     => $city,
        ':district' => $district,
        ':name'     => $neighborhoodName,
    ]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        return (int) $existing;
    }

    $slug = slugify($city . '-' . $district . '-' . $neighborhoodName);
    $ins  = $pdo->prepare("
        INSERT INTO neighborhoods (city, district, name, slug)
        VALUES (:city, :district, :name, :slug)
    ");
    $ins->execute([
        ':city'     => $city,
        ':district' => $district,
        ':name'     => $neighborhoodName,
        ':slug'     => $slug,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Kullanıcı Kayıt İşlemi
 */
function register_user(array $input): array
{
    $pdo = db();

    $mode          = trim($input['register_mode'] ?? 'individual');
    $fullName      = trim($input['full_name'] ?? '');
    $email         = mb_strtolower(trim($input['email'] ?? ''), 'UTF-8');
    $password      = $input['password'] ?? '';
    $residentCount = max(1, min(20, (int) ($input['resident_count'] ?? 2)));

    if (mb_strlen($fullName, 'UTF-8') < 3) {
        return ['ok' => false, 'error' => 'Lütfen adınızı ve soyadınızı eksiksiz girin.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Geçerli bir e-posta adresi girin.'];
    }
    if (strlen($password) < 6) {
        return ['ok' => false, 'error' => 'Şifreniz en az 6 karakter olmalıdır.'];
    }

    // E-posta kontrolü
    $chk = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $chk->execute([':email' => $email]);
    if ($chk->fetchColumn()) {
        return ['ok' => false, 'error' => 'Bu e-posta adresi ile daha önce kayıt olunmuş.'];
    }

    $palette = ['#1b5670', '#2b6e46', '#7a4b2a', '#3b5968', '#4f6248', '#8a5a36'];
    $avatarColor = $palette[array_rand($palette)];

    try {
        $pdo->beginTransaction();

        $neighborhoodId = null;
        $apartmentId    = null;
        $housingType    = 'individual_unit';
        $unitLabel      = 'Bireysel Hane';
        $role           = 'resident';
        $invitationId   = null;

        if ($mode === 'individual') {
            // 1. Bireysel (Apartmanı olmayan daire) veya Müstakil Ev Katılımı
            $city             = trim($input['city'] ?? '');
            $district         = trim($input['district'] ?? '');
            $neighborhoodName = trim($input['neighborhood_name'] ?? '');
            $subType          = trim($input['individual_housing_type'] ?? 'individual_unit');
            $customLabel      = trim($input['unit_label'] ?? '');

            if ($city === '' || $district === '' || $neighborhoodName === '') {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Mahalleye katılmak için İl, İlçe ve Mahalle bilgilerini girin.'];
            }

            $neighborhoodId = find_or_create_neighborhood($pdo, $city, $district, $neighborhoodName);
            $housingType    = ($subType === 'detached_house') ? 'detached_house' : 'individual_unit';

            if ($customLabel !== '') {
                $unitLabel = $customLabel;
            } else {
                $unitLabel = ($housingType === 'detached_house') ? 'Müstakil Ev' : 'Bireysel Daire';
            }
        } elseif ($mode === 'apartment_invite') {
            // 2. Davet Kodu ile Apartmana Katılım (Genel kod veya daireye özel token)
            $inviteCode  = strtoupper(trim($input['invite_code'] ?? ''));
            $customLabel = trim($input['unit_label'] ?? '');

            if ($inviteCode === '') {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Lütfen apartman yöneticinizden aldığınız davet kodunu girin.'];
            }

            // Önce daireye özel davet tablosuna bak
            $invStmt = $pdo->prepare("
                SELECT i.*, a.neighborhood_id
                FROM invitations i
                INNER JOIN apartments a ON a.id = i.apartment_id
                WHERE i.invite_token = :token AND i.is_used = 0
                LIMIT 1
            ");
            $invStmt->execute([':token' => $inviteCode]);
            $invitation = $invStmt->fetch();

            if ($invitation) {
                $apartmentId    = (int) $invitation['apartment_id'];
                $neighborhoodId = (int) $invitation['neighborhood_id'];
                $unitLabel      = $invitation['unit_label'];
                $invitationId   = (int) $invitation['id'];
                $housingType    = 'apartment_unit';
            } else {
                // Genel apartman davet koduna bak
                $aptStmt = $pdo->prepare("
                    SELECT id, neighborhood_id
                    FROM apartments
                    WHERE invite_code = :code
                    LIMIT 1
                ");
                $aptStmt->execute([':code' => $inviteCode]);
                $apt = $aptStmt->fetch();

                if (!$apt) {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'Geçersiz veya kullanılmış davet kodu. Lütfen kontrol edin.'];
                }

                if ($customLabel === '') {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'Lütfen daire numaranızı belirtin (Örn: Daire 4).'];
                }

                $apartmentId    = (int) $apt['id'];
                $neighborhoodId = (int) $apt['neighborhood_id'];
                $unitLabel      = $customLabel;
                $housingType    = 'apartment_unit';
            }
        } elseif ($mode === 'apartment_manager') {
            // 3. Apartman Yöneticisi Olarak Yeni Apartman + Mahalle Kaydı
            $city             = trim($input['city'] ?? '');
            $district         = trim($input['district'] ?? '');
            $neighborhoodName = trim($input['neighborhood_name'] ?? '');
            $apartmentName    = trim($input['apartment_name'] ?? '');
            $addressDetail    = trim($input['address_detail'] ?? '');
            $totalUnits       = max(2, min(500, (int) ($input['total_units'] ?? 12)));
            $customLabel      = trim($input['unit_label'] ?? 'Daire 1 (Yönetici)');

            if ($city === '' || $district === '' || $neighborhoodName === '' || $apartmentName === '') {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Apartman kaydı için İl, İlçe, Mahalle ve Apartman Adı zorunludur.'];
            }

            $neighborhoodId = find_or_create_neighborhood($pdo, $city, $district, $neighborhoodName);

            // Benzersiz davet kodu üret
            $inviteCode = generate_invite_code('DEBI');
            $aptIns = $pdo->prepare("
                INSERT INTO apartments (neighborhood_id, name, address_detail, invite_code, total_units)
                VALUES (:nid, :name, :addr, :code, :units)
            ");
            $aptIns->execute([
                ':nid'   => $neighborhoodId,
                ':name'  => $apartmentName,
                ':addr'  => $addressDetail ?: null,
                ':code'  => $inviteCode,
                ':units' => $totalUnits,
            ]);
            $apartmentId = (int) $pdo->lastInsertId();
            $housingType = 'apartment_unit';
            $unitLabel   = $customLabel !== '' ? $customLabel : 'Daire 1';
            $role        = 'manager';
        } else {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Geçersiz kayıt türü seçildi.'];
        }

        // Hane (household) oluştur
        $hhStmt = $pdo->prepare("
            INSERT INTO households (neighborhood_id, apartment_id, housing_type, unit_label, resident_count)
            VALUES (:nid, :aid, :htype, :label, :rcount)
        ");
        $hhStmt->execute([
            ':nid'    => $neighborhoodId,
            ':aid'    => $apartmentId,
            ':htype'  => $housingType,
            ':label'  => $unitLabel,
            ':rcount' => $residentCount,
        ]);
        $householdId = (int) $pdo->lastInsertId();

        // Kullanıcı (user) oluştur
        $userStmt = $pdo->prepare("
            INSERT INTO users (household_id, neighborhood_id, apartment_id, full_name, email, password_hash, role, avatar_color)
            VALUES (:hid, :nid, :aid, :name, :email, :pass, :role, :color)
        ");
        $userStmt->execute([
            ':hid'   => $householdId,
            ':nid'   => $neighborhoodId,
            ':aid'   => $apartmentId,
            ':name'  => $fullName,
            ':email' => $email,
            ':pass'  => password_hash($password, PASSWORD_DEFAULT),
            ':role'  => $role,
            ':color' => $avatarColor,
        ]);
        $userId = (int) $pdo->lastInsertId();

        // Eğer apartman yöneticisiyse apartments.manager_user_id güncelle
        if ($role === 'manager' && $apartmentId) {
            $updApt = $pdo->prepare("UPDATE apartments SET manager_user_id = :uid WHERE id = :aid");
            $updApt->execute([':uid' => $userId, ':aid' => $apartmentId]);
        }

        // Eğer özel davet kullanıldıysa işaretle
        if ($invitationId) {
            $updInv = $pdo->prepare("
                UPDATE invitations
                SET is_used = 1, used_by_user_id = :uid, used_at = NOW()
                WHERE id = :id
            ");
            $updInv->execute([':uid' => $userId, ':id' => $invitationId]);
        }

        $pdo->commit();
        return ['ok' => true, 'user_id' => $userId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => 'Kayıt sırasında bir hata oluştu: ' . $e->getMessage()];
    }
}

/**
 * Kullanıcı Giriş İşlemi
 */
function login_user(string $email, string $password): array
{
    $email = mb_strtolower(trim($email), 'UTF-8');
    if ($email === '' || $password === '') {
        return ['ok' => false, 'error' => 'E-posta ve şifre alanları boş bırakılamaz.'];
    }

    $stmt = db()->prepare("SELECT id, password_hash FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['ok' => false, 'error' => 'E-posta adresi veya şifre hatalı.'];
    }

    return ['ok' => true, 'user_id' => (int) $user['id']];
}

