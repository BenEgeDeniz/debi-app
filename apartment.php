<?php
/**
 * DEBI - Apartman & Dairem Sayfası
 * Net ayrım:
 * 1. Apartman Kartı -> Apartman Yöneticisi bilgisi + Apartman Kodu (DEBI-XXXXX: yeni dairelerin katılması için)
 * 2. Dairem Kartı   -> Daire Yöneticisi / Sakin bilgisi + Daire Sakin Kodu (DAIRE-XXXXX: aynı dairede yaşayanlar için)
 * 3. Daireler Listesi -> Apartmandaki dairelerin sade aylık su tüketim tablosu
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/community/community_service.php';

$currentUser   = require_login();
$isUnitManager = is_unit_manager($currentUser);

// Müstakil ev kullanıcıları apartmana katılamaz veya apartman oluşturamaz
if (empty($currentUser['apartment_id']) && ($currentUser['housing_type'] ?? '') === 'detached_house') {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Güvenlik doğrulaması başarısız oldu.');
        redirect('apartment.php');
    }

    $action = $_POST['action'] ?? '';

    // 1. Davet koduyla apartmana (DEBI-...) veya mevcut bir daireye sakin olarak (DAIRE-...) katılma
    if ($action === 'join_apartment') {
        $code  = $_POST['invite_code'] ?? '';
        $label = $_POST['unit_label'] ?? '';
        $res   = join_apartment_for_existing_user($currentUser, $code, $label);
        if ($res['ok']) {
            if (($res['joined_as'] ?? '') === 'resident') {
                set_flash('success', format_unit_label($res['unit_label'] ?? 'Daire') . ' hanesine daire sakini olarak katıldınız!');
            } else {
                set_flash('success', 'Apartmanınıza daire yöneticisi olarak bağlandınız!');
            }
        } else {
            set_flash('error', $res['error']);
        }
        redirect('apartment.php');
    }

    // 2. Bireysel kullanıcının kendi mahallesinde apartman oluşturması
    if ($action === 'create_apartment' && empty($currentUser['apartment_id'])) {
        $name  = $_POST['apartment_name'] ?? '';
        $addr  = $_POST['address_detail'] ?? '';
        $units = (int) ($_POST['total_units'] ?? 12);
        $label = $_POST['unit_label'] ?? 'Daire 1';
        $res   = create_apartment_for_existing_user($currentUser, $name, $addr, $units, $label);
        if ($res['ok']) {
            set_flash('success', 'Apartmanınız oluşturuldu. Apartman davet kodunu komşularınızla paylaşabilirsiniz.');
        } else {
            set_flash('error', $res['error']);
        }
        redirect('apartment.php');
    }
}

$year  = (int) date('Y');
$month = (int) date('n');

$aptData = !empty($currentUser['apartment_id'])
    ? get_apartment_overview((int) $currentUser['apartment_id'], $year, $month)
    : null;

$myResidents   = get_household_residents((int) $currentUser['household_id']);
$myUnitLabel   = format_unit_label($currentUser['unit_label'] ?? '', $currentUser['housing_type'] ?? 'apartment_unit');

$pageTitle = !empty($currentUser['apartment_name'])
    ? $currentUser['apartment_name'] . ' — Apartman & Dairem — Debi'
    : 'Apartman & Daire Bağlantısı — Debi';
$activeNav = 'apartment';
require_once __DIR__ . '/includes/header.php';
?>

<div class="app-shell app-shell-2col">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

    <main class="main-column">
        <?php if (empty($currentUser['apartment_id'])): ?>
            <!-- DURUM A: Kullanıcı henüz bir apartmana bağlı değil -->
            <div class="grid-2-cols">
                <section class="panel-card">
                    <h2 class="section-title">1. Davet Kodu ile Katıl</h2>
                    <p class="panel-desc">
                        Apartman kodu (<code>DEBI-...</code>) ile kendi dairenizi açabilir veya daire kodu (<code>DAIRE-...</code>) ile mevcut bir daireye sakin olarak katılabilirsiniz.
                    </p>
                    <form method="post" action="apartment.php" class="form-stack">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="join_apartment">

                        <div class="form-group">
                            <label for="invite_code">Davet Kodu</label>
                            <input type="text" id="invite_code" name="invite_code" required placeholder="Örn: DEBI-7K4M2 veya DAIRE-9P2X4">
                        </div>

                        <div class="form-group">
                            <label for="join_unit_label">Daire Numaranız (Apartman kodu için)</label>
                            <input type="text" id="join_unit_label" name="unit_label" placeholder="Örn: Daire 4 (Daire kodunda boş bırakın)">
                        </div>

                        <button type="submit" class="btn btn-primary">Bağlan</button>
                    </form>
                </section>

                <section class="panel-card">
                    <h2 class="section-title">2. Yeni Apartman Oluştur</h2>
                    <p class="panel-desc">
                        <strong><?= e($currentUser['neighborhood_name']) ?></strong> mahallesindeki apartmanınızı kurup <strong>Apartman Yöneticisi</strong> olun.
                    </p>
                    <form method="post" action="apartment.php" class="form-stack">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create_apartment">

                        <div class="form-group">
                            <label for="apartment_name">Apartman / Site Adı</label>
                            <input type="text" id="apartment_name" name="apartment_name" required placeholder="Örn: Palmiye Apartmanı">
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label for="total_units">Toplam Daire Sayısı</label>
                                <input type="number" id="total_units" name="total_units" min="2" max="500" value="12" required>
                            </div>
                            <div class="form-group">
                                <label for="create_unit_label">Sizin Daireniz</label>
                                <input type="text" id="create_unit_label" name="unit_label" value="Daire 1" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-outline">Apartmanı Oluştur</button>
                    </form>
                </section>
            </div>
        <?php else: ?>
            <!-- DURUM B: Kullanıcı bir apartmana bağlı -->
            <?php
            $apt      = $aptData['apartment'];
            $units    = $aptData['units'];
            $stats    = $aptData['stats'];
            $avgDaily = $stats['avg_daily_liters'] !== null ? (float) $stats['avg_daily_liters'] : null;
            ?>

            <!-- 1. KART: APARTMAN BİLGİSİ VE APARTMAN KODU -->
            <section class="panel-card clear-role-section">
                <div class="clear-section-top">
                    <div>
                        <span class="clear-kicker">1. APARTMAN BİLGİSİ</span>
                        <h1 class="page-title">🏢 <?= e($apt['name']) ?></h1>
                        <p class="page-subtitle">
                            <?= e($apt['neighborhood_name']) ?> &middot; <?= e($apt['district'] . ' / ' . $apt['city']) ?>
                            &middot; Kayıtlı Daire: <strong><?= count($units) ?> / <?= (int) $apt['total_units'] ?></strong>
                            <?php if ($avgDaily !== null): ?>
                                &middot; Apartman Ortalaması: <strong><?= number_format($avgDaily, 1, ',', '.') ?> L / gün</strong>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div>
                        <?php if ($currentUser['role'] === 'manager'): ?>
                            <span class="role-badge role-badge-apt">🏢 Apartman Yöneticisisiniz</span>
                        <?php else: ?>
                            <span class="role-badge">Apartman Yöneticisi: <?= e($apt['manager_name'] ?? 'Belirtilmedi') ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="code-share-strip strip-apt">
                    <div class="code-share-info">
                        <strong>Apartman Davet Kodu (Yeni Daireler İçin)</strong>
                        <span>Apartmandaki diğer komşuların kendi dairelerini oluşturup apartmana katılması için bu kodu paylaşın:</span>
                    </div>
                    <div class="code-share-box">
                        <code><?= e($apt['invite_code']) ?></code>
                        <button type="button" class="btn btn-primary btn-sm btn-copy" data-copy="<?= e($apt['invite_code']) ?>">Kodu Kopyala</button>
                    </div>
                </div>
            </section>

            <!-- 2. KART: BENİM DAİREM VE DAİRE SAKİN KODU -->
            <section class="panel-card clear-role-section">
                <div class="clear-section-top">
                    <div>
                        <span class="clear-kicker">2. DAİREM &amp; EV SAKİNLERİ</span>
                        <h2 class="page-title">🏠 <?= e($myUnitLabel) ?></h2>
                        <p class="page-subtitle">
                            Bu dairede toplam <strong><?= (int) $currentUser['resident_count'] ?> kişi</strong> yaşıyor
                            &middot; Sistemde kayıtlı hesap: <strong><?= count($myResidents) ?></strong>
                        </p>
                    </div>

                    <div>
                        <?php if ($isUnitManager): ?>
                            <span class="role-badge role-badge-unit">🏠 <?= e($myUnitLabel) ?> Yöneticisisiniz</span>
                        <?php else: ?>
                            <span class="role-badge role-badge-resident">👤 <?= e($myUnitLabel) ?> Sakinisiniz</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($isUnitManager && !empty($currentUser['household_invite_code'])): ?>
                    <div class="code-share-strip strip-unit">
                        <div class="code-share-info">
                            <strong>Daire Sakin Kodu (Aynı Dairede Yaşayanlar İçin)</strong>
                            <span>Aynı evde yaşadığınız eşinizi, aile üyenizi veya ev arkadaşınızı <strong><?= e($myUnitLabel) ?></strong> içerisine sakin olarak eklemek için bu kodu paylaşın:</span>
                        </div>
                        <div class="code-share-box">
                            <code><?= e($currentUser['household_invite_code']) ?></code>
                            <button type="button" class="btn btn-outline btn-sm btn-copy" data-copy="<?= e($currentUser['household_invite_code']) ?>">Kodu Kopyala</button>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="unit-members-row">
                    <span class="sub-label"><?= e($myUnitLabel) ?> Sakinleri:</span>
                    <div class="unit-residents-list">
                        <?php foreach ($myResidents as $resUser): ?>
                            <?php $isOwner = ((int) ($resUser['owner_user_id'] ?? 0) === (int) $resUser['id']); ?>
                            <a href="profile.php?id=<?= (int) $resUser['id'] ?>" class="unit-resident-chip">
                                <?= render_avatar($resUser, 'avatar-xs') ?>
                                <span class="unit-resident-name"><?= e($resUser['full_name']) ?></span>
                                <span class="tag"><?= $isOwner ? 'Daire Yöneticisi' : 'Daire Sakini' ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <!-- 3. KART: APARTMANDAKİ DAİRELER LİSTESİ -->
            <section class="panel-card">
                <div class="panel-card-header">
                    <h2 class="section-title">Apartmandaki Daireler (<?= e(turkish_month_name($month) . ' ' . $year) ?>)</h2>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Daire</th>
                                <th>Daire Yöneticisi &amp; Sakinler</th>
                                <th>Bu Ayki Sayaç</th>
                                <th>Kişi Başı Günlük</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($units as $unit): ?>
                                <?php
                                $isMyUnit  = (int) $unit['household_id'] === (int) $currentUser['household_id'];
                                $unitDaily = $unit['daily_liters_per_person'] !== null ? (float) $unit['daily_liters_per_person'] : null;
                                $ownerId   = (int) ($unit['owner_user_id'] ?? 0);
                                $uLabel    = format_unit_label($unit['unit_label'], 'apartment_unit');
                                ?>
                                <tr class="<?= $isMyUnit ? 'row-highlight' : '' ?>">
                                    <td>
                                        <strong><?= e($uLabel) ?></strong>
                                        <div class="table-sub"><?= (int) $unit['resident_count'] ?> kişi</div>
                                    </td>
                                    <td>
                                        <div class="residents-stack">
                                            <?php foreach ($unit['residents'] as $r): ?>
                                                <div class="resident-cell">
                                                    <a href="profile.php?id=<?= (int) $r['id'] ?>" class="author-link">
                                                        <strong><?= e($r['full_name']) ?></strong>
                                                    </a>
                                                    <?php if ((int) $r['id'] === $ownerId): ?>
                                                        <span class="tag">Daire Yöneticisi</span>
                                                    <?php else: ?>
                                                        <span class="tag">Sakin</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?= $unit['consumption_m3'] !== null ? number_format((float) $unit['consumption_m3'], 1, ',', '.') . ' m³' : '<span class="muted-text">Fatura girilmedi</span>' ?>
                                    </td>
                                    <td>
                                        <?php if ($unitDaily !== null): ?>
                                            <span class="metric-pill pill-good"><?= number_format($unitDaily, 1, ',', '.') ?> L / gün</span>
                                        <?php else: ?>
                                            <span class="muted-text">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
