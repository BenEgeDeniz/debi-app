<?php
/**
 * DEBI - Kimlik Doğrulama ve Kayıt Modülü
 * Modlar:
 * 1) individual        -> Bireysel Daire veya Müstakil Ev olarak katılım (Daire/Hane Yöneticisi olur)
 * 2) apartment_invite  ->
 *      a) Apartman Kodu (DEBI-XXXXX) ile apartmanda yeni daire açıp Daire Yöneticisi olarak katılım
 *      b) Daire Sakin Kodu (DAIRE-XXXXX) ile mevcut bir daireye Sakin olarak katılım
 * 3) apartment_manager -> Mahallede yeni Apartman oluşturup Apartman + Daire Yöneticisi olarak katılım
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
 * Davet kodunun türünü ve mevcut hedefini kayıt formuna döndürür.
 */
function lookup_registration_invite(string $inviteCode): array
{
    $inviteCode = strtoupper(trim($inviteCode));
    if (!preg_match('/^(DEBI|DAIRE)-[A-Z0-9]+$/', $inviteCode, $matches)) {
        return ['ok' => false, 'error' => 'Kod DEBI-... veya DAIRE-... biçiminde olmalı.'];
    }

    $pdo = db();
    if ($matches[1] === 'DAIRE') {
        $stmt = $pdo->prepare("
            SELECT unit_label
            FROM households
            WHERE unit_invite_code = :code
            LIMIT 1
        ");
        $stmt->execute([':code' => $inviteCode]);
        $unitLabel = $stmt->fetchColumn();
        if ($unitLabel === false) {
            return ['ok' => false, 'error' => 'Bu daire davet kodu bulunamadı.'];
        }
        return [
            'ok' => true,
            'type' => 'resident',
            'label' => 'Daire Sakini',
            'unit_label' => (string) $unitLabel,
        ];
    }

    $stmt = $pdo->prepare("
        SELECT name
        FROM apartments
        WHERE invite_code = :code
        LIMIT 1
    ");
    $stmt->execute([':code' => $inviteCode]);
    $apartmentName = $stmt->fetchColumn();
    if ($apartmentName === false) {
        return ['ok' => false, 'error' => 'Bu apartman davet kodu bulunamadı.'];
    }

    return [
        'ok' => true,
        'type' => 'manager',
        'label' => 'Daire Yöneticisi',
        'apartment_name' => (string) $apartmentName,
    ];
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

    $palette = ['#0284c7', '#15803d', '#0f766e', '#3b5968', '#1e40af', '#8a5a36'];
    $avatarColor = $palette[array_rand($palette)];

    try {
        $pdo->beginTransaction();

        $neighborhoodId      = null;
        $apartmentId         = null;
        $existingHouseholdId = null;
        $housingType         = 'individual_unit';
        $unitLabel           = 'Bireysel Hane';
        $role                = 'resident';

        if ($mode === 'individual') {
            // 1. Bireysel Daire veya Müstakil Ev Katılımı (Kendi hanesinin Daire/Ev Yöneticisi olur)
            $city             = trim($input['city'] ?? '');
            $district         = trim($input['district'] ?? '');
            $neighborhoodName = trim($input['neighborhood_name'] ?? '');
            $subType          = trim($input['individual_housing_type'] ?? 'individual_unit');
            $customLabel      = trim($input['unit_label'] ?? '');

            if ($city === '' || $district === '' || $neighborhoodName === '') {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Lütfen İl, İlçe ve Mahalle bilgilerini seçin.'];
            }

            $neighborhoodId = find_or_create_neighborhood($pdo, $city, $district, $neighborhoodName);
            $housingType    = ($subType === 'detached_house') ? 'detached_house' : 'individual_unit';

            if ($customLabel !== '') {
                $unitLabel = $customLabel;
            } else {
                $unitLabel = ($housingType === 'detached_house') ? 'Müstakil Ev' : 'Bireysel Daire';
            }
        } elseif ($mode === 'apartment_invite') {
            // 2. Davet Kodu ile Katılım:
            //    a) Daire Sakin Kodu (DAIRE-XXXXX) -> Mevcut daireye "Sakin" olarak katılır
            //    b) Apartman Kodu (DEBI-XXXXX)     -> Apartmanda kendi dairesini oluşturup "Daire Yöneticisi" olur
            $inviteCode  = strtoupper(trim($input['invite_code'] ?? ''));
            $customLabel = trim($input['unit_label'] ?? '');

            if ($inviteCode === '') {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Lütfen apartman veya daire davet kodunu girin.'];
            }

            // A) Önce Daire Sakin Davet Kodu (households.unit_invite_code) kontrolü
            $hhStmt = $pdo->prepare("
                SELECT id, neighborhood_id, apartment_id, housing_type, unit_label
                FROM households
                WHERE unit_invite_code = :code
                LIMIT 1
            ");
            $hhStmt->execute([':code' => $inviteCode]);
            $existingHh = $hhStmt->fetch();

            if ($existingHh) {
                $existingHouseholdId = (int) $existingHh['id'];
                $neighborhoodId      = (int) $existingHh['neighborhood_id'];
                $apartmentId         = !empty($existingHh['apartment_id']) ? (int) $existingHh['apartment_id'] : null;
                $housingType         = $existingHh['housing_type'];
                $unitLabel           = $existingHh['unit_label'];
            } else {
                // B) Genel Apartman Davet Kodu (apartments.invite_code) kontrolü
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
                    return ['ok' => false, 'error' => 'Geçersiz davet kodu. Lütfen apartman kodunu (DEBI-...) veya daire sakin kodunu (DAIRE-...) kontrol edin.'];
                }

                if ($customLabel === '') {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'Apartman davet kodu ile yeni daire oluştururken daire numaranızı (Örn: Daire 4) belirtmelisiniz.'];
                }

                $apartmentId    = (int) $apt['id'];
                $neighborhoodId = (int) $apt['neighborhood_id'];
                $unitLabel      = $customLabel;
                $housingType    = 'apartment_unit';
                $role           = 'manager';
            }
        } elseif ($mode === 'apartment_manager') {
            // 3. Apartman Yöneticisi Olarak Yeni Apartman + Kendi Dairesini Oluşturma
            $city             = trim($input['city'] ?? '');
            $district         = trim($input['district'] ?? '');
            $neighborhoodName = trim($input['neighborhood_name'] ?? '');
            $apartmentName    = trim($input['apartment_name'] ?? '');
            $addressDetail    = trim($input['address_detail'] ?? '');
            $totalUnits       = max(2, min(500, (int) ($input['total_units'] ?? 12)));
            $customLabel      = trim($input['unit_label'] ?? 'Daire 1');

            if ($city === '' || $district === '' || $neighborhoodName === '' || $apartmentName === '') {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Apartman kaydı için İl, İlçe, Mahalle ve Apartman Adı zorunludur.'];
            }

            $neighborhoodId = find_or_create_neighborhood($pdo, $city, $district, $neighborhoodName);

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

        // Eğer mevcut bir daireye sakin olarak katılmıyorsa yeni daire (household) oluştur
        if ($existingHouseholdId !== null) {
            $householdId = $existingHouseholdId;
        } else {
            $unitInviteCode = generate_invite_code('DAIRE');
            $hhStmt = $pdo->prepare("
                INSERT INTO households (neighborhood_id, apartment_id, housing_type, unit_label, resident_count, unit_invite_code)
                VALUES (:nid, :aid, :htype, :label, :rcount, :ucode)
            ");
            $hhStmt->execute([
                ':nid'    => $neighborhoodId,
                ':aid'    => $apartmentId,
                ':htype'  => $housingType,
                ':label'  => $unitLabel,
                ':rcount' => $residentCount,
                ':ucode'  => $unitInviteCode,
            ]);
            $householdId = (int) $pdo->lastInsertId();
        }

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

        // Yeni oluşturulan dairenin Daire Yöneticisi (owner_user_id) bu kullanıcıdır
        if ($existingHouseholdId === null) {
            $pdo->prepare("UPDATE households SET owner_user_id = :uid WHERE id = :hid")
                ->execute([':uid' => $userId, ':hid' => $householdId]);
        } else {
            // Mevcut daireye sakin katıldıysa kişi sayısının kayıtlı sakin sayısından az olmamasını sağla
            $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE household_id = :hid");
            $cntStmt->execute([':hid' => $householdId]);
            $regMembers = (int) $cntStmt->fetchColumn();
            $pdo->prepare("UPDATE households SET resident_count = GREATEST(resident_count, :cnt) WHERE id = :hid")
                ->execute([':cnt' => $regMembers, ':hid' => $householdId]);
        }

        // Eğer apartman yöneticisiyse apartments.manager_user_id güncelle
        if ($role === 'manager' && $apartmentId) {
            $updApt = $pdo->prepare("UPDATE apartments SET manager_user_id = :uid WHERE id = :aid");
            $updApt->execute([':uid' => $userId, ':aid' => $apartmentId]);
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
