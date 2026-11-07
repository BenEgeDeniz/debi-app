<?php
/**
 * DEBI - Apartman Sayfası, Daire Yöneticisi Sakin Daveti ve Tüketim Tablosu
 * - Apartman Yöneticisi yalnızca genel Apartman Davet Kodunu (DEBI-XXXXX) paylaşır.
 * - Daireyi oluşturan kişi Daire Yöneticisi olur ve Daire Sakin Kodu (DAIRE-XXXXX) ile dairesine sakin davet edebilir.
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
                set_flash('success', ($res['unit_label'] ?? 'Daire') . ' hanesine daire sakini olarak katıldınız!');
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

    // 3. Daire Yöneticisinin kendi dairesinin Sakin Davet Kodunu (DAIRE-XXXXX) yenilemesi
    if ($action === 'regenerate_unit_code' && $isUnitManager) {
        $res = regenerate_household_invite_code((int) $currentUser['household_id'], (int) $currentUser['id']);
        if ($res['ok']) {
            set_flash('success', 'Daire sakin davet kodunuz yenilendi: ' . $res['code']);
        } else {
            set_flash('error', $res['error']);
        }
        redirect('apartment.php');
    }

    // 4. Apartman yöneticisinin aylık su hedefini güncellemesi
    if ($action === 'update_goal' && !empty($currentUser['apartment_id']) && $currentUser['role'] === 'manager') {
        $goal = trim($_POST['monthly_goal_m3'] ?? '');
        $val  = $goal !== '' ? (float) str_replace(',', '.', $goal) : null;
        update_apartment_goal((int) $currentUser['apartment_id'], $val);
        set_flash('success', 'Apartman aylık su hedefi güncellendi.');
        redirect('apartment.php');
    }
}

$year  = isset($_GET['year']) ? max(2020, min(2035, (int) $_GET['year'])) : (int) date('Y');
$month = isset($_GET['month']) ? max(1, min(12, (int) $_GET['month'])) : (int) date('n');

$aptData = !empty($currentUser['apartment_id'])
    ? get_apartment_overview((int) $currentUser['apartment_id'], $year, $month)
    : null;

$myResidents = get_household_residents((int) $currentUser['household_id']);

$pageTitle = !empty($currentUser['apartment_name'])
    ? $currentUser['apartment_name'] . ' — Apartmanım — Debi'
    : 'Apartman & Daire Bağlantısı — Debi';
$activeNav = 'apartment';
require_once __DIR__ . '/includes/header.php';
?>

<div class="app-shell app-shell-2col">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

    <main class="main-column">
        <?php if (empty($currentUser['apartment_id'])): ?>
            <!-- DURUM A: Kullanıcı henüz bir apartmana bağlı değil -->
            <section class="panel-card page-hero-card">
                <div class="section-kicker">APARTMAN &amp; DAİRE BAĞLANTISI</div>
                <h1 class="page-title">Apartmana veya Mevcut Bir Daireye Katılın</h1>
                <p class="page-subtitle">
                    Apartman yöneticinizin paylaştığı <strong>Apartman Kodu (<code>DEBI-...</code>)</strong> ile kendi dairenizi oluşturup <strong>Daire Yöneticisi</strong> olabilir veya daire yöneticinizin paylaştığı <strong>Daire Kodu (<code>DAIRE-...</code>)</strong> ile mevcut bir daireye <strong>Sakin</strong> olarak katılabilirsiniz.
                </p>
            </section>

            <div class="grid-2-cols">
                <section class="panel-card">
                    <h2 class="section-title">1. Davet Kodu ile Katıl</h2>
                    <p class="panel-desc">
                        Apartman kodu (<code>DEBI-XXXXX</code>) veya daire sakin kodu (<code>DAIRE-XXXXX</code>) girin:
                    </p>
                    <form method="post" action="apartment.php" class="form-stack">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="join_apartment">

                        <div class="form-group">
                            <label for="invite_code">Apartman veya Daire Sakin Kodu</label>
                            <input type="text" id="invite_code" name="invite_code" required placeholder="Örn: DEBI-7K4M2 veya DAIRE-9P2X4">
                        </div>

                        <div class="form-group">
                            <label for="join_unit_label">Daire Numaranız (Apartman kodu için)</label>
                            <input type="text" id="join_unit_label" name="unit_label" placeholder="Örn: Daire 4 (Daire sakin kodunda boş bırakılabilir)">
                        </div>

                        <button type="submit" class="btn btn-primary">Bağlan</button>
                    </form>
                </section>

                <section class="panel-card">
                    <h2 class="section-title">2. Apartmanınızı Siz Oluşturun</h2>
                    <p class="panel-desc">
                        <strong><?= e($currentUser['neighborhood_name']) ?></strong> mahallesindeki apartmanınızı kaydedip apartman yöneticisi olun ve komşularınızla apartman kodunu paylaşın.
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

                        <div class="form-group">
                            <label for="address_detail">Sokak / Kapı No (İsteğe bağlı)</label>
                            <input type="text" id="address_detail" name="address_detail" placeholder="Örn: Karanfil Sok. No: 9">
                        </div>

                        <button type="submit" class="btn btn-outline">Apartmanı Oluştur ve Yönetici Ol</button>
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
            <!-- 1. Apartman Özeti ve Genel Apartman Davet Kodu -->
            <section class="panel-card page-hero-card">
                <div class="page-hero-top">
                    <div>
                        <div class="section-kicker">
                            <?= e($apt['neighborhood_name']) ?> &middot; APARTMAN TOPLULUĞU
                        </div>
                        <h1 class="page-title"><?= e($apt['name']) ?></h1>
                        <p class="page-subtitle">
                            <?= e($apt['district'] . ' / ' . $apt['city']) ?>
                            <?= !empty($apt['address_detail']) ? ' · ' . e($apt['address_detail']) : '' ?>
                            · Apartman Yöneticisi: <strong><?= e($apt['manager_name'] ?? 'Belirtilmedi') ?></strong>
                        </p>
                    </div>

                    <div class="invite-badge-card">
                        <span class="invite-badge-title">Apartman Davet Kodu</span>
                        <div class="invite-badge-code">
                            <code><?= e($apt['invite_code']) ?></code>
                            <button type="button" class="btn-copy" data-copy="<?= e($apt['invite_code']) ?>">Kopyala</button>
                        </div>
                        <span class="invite-badge-hint">Yeni daireler apartmana bu kodla katılır</span>
                    </div>
                </div>

                <div class="stats-grid-4">
                    <div class="stat-box">
                        <span class="stat-label">Kayıtlı Daireler</span>
                        <strong class="stat-value"><?= count($units) ?> / <?= (int) $apt['total_units'] ?></strong>
                        <span class="stat-sub">Aktif daire</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-label">Fatura Giren Daire</span>
                        <strong class="stat-value"><?= (int) ($stats['logged_units_count'] ?? 0) ?> daire</strong>
                        <span class="stat-sub"><?= e(turkish_month_name($month) . ' ' . $year) ?></span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-label">Apartman Ortalaması</span>
                        <strong class="stat-value">
                            <?= $avgDaily !== null ? number_format($avgDaily, 1, ',', '.') . ' L' : '—' ?>
                        </strong>
                        <span class="stat-sub">kişi başı / gün</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-label">Aylık Toplam Sayaç</span>
                        <strong class="stat-value">
                            <?= $stats['total_m3'] !== null ? number_format((float) $stats['total_m3'], 1, ',', '.') . ' m³' : '0 m³' ?>
                        </strong>
                        <?php if (!empty($apt['monthly_goal_m3'])): ?>
                            <span class="stat-sub">Hedef: <?= number_format((float) $apt['monthly_goal_m3'], 0, ',', '.') ?> m³</span>
                        <?php else: ?>
                            <span class="stat-sub">Hedef belirlenmedi</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($currentUser['role'] === 'manager'): ?>
                    <div class="apt-goal-inline-bar">
                        <div class="apt-goal-inline-text">
                            <strong>Apartman Aylık Su Hedefi (m³)</strong>
                            <span>Apartman yöneticisi olarak tüm dairelerin toplam aylık tüketim hedefini belirleyebilirsiniz.</span>
                        </div>
                        <form method="post" action="apartment.php" class="inline-action-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_goal">
                            <input type="number" step="0.5" min="5" max="10000" name="monthly_goal_m3"
                                   value="<?= e((string) ($apt['monthly_goal_m3'] ?? '')) ?>" placeholder="Örn: 120 m³">
                            <button type="submit" class="btn btn-outline btn-sm">Hedefi Kaydet</button>
                        </form>
                    </div>
                <?php endif; ?>
            </section>

            <!-- 2. Dairem & Daire Sakinleri (Daire Yöneticisi Sakin Davet Paneli) -->
            <section class="panel-card unit-management-card">
                <div class="panel-card-header">
                    <div>
                        <div class="section-kicker">DAİREM &amp; EV SAKİNLERİ</div>
                        <h2 class="section-title">
                            <?= e($currentUser['unit_label']) ?>
                            <span class="muted-text" style="font-weight: 600;">(Toplam <?= (int) $currentUser['resident_count'] ?> kişi)</span>
                        </h2>
                    </div>
                    <?php if ($isUnitManager): ?>
                        <span class="tag tag-manager">Daire Yöneticisisiniz</span>
                    <?php else: ?>
                        <span class="tag">Daire Sakini</span>
                    <?php endif; ?>
                </div>

                <div class="unit-panel-grid">
                    <!-- Sol: Dairedeki Kayıtlı Sakinler -->
                    <div class="unit-residents-box">
                        <span class="sub-label">Dairede Kayıtlı Hesaplar (<?= count($myResidents) ?>)</span>
                        <div class="unit-residents-list">
                            <?php foreach ($myResidents as $resUser): ?>
                                <?php $isOwner = ((int) ($resUser['owner_user_id'] ?? 0) === (int) $resUser['id']); ?>
                                <a href="profile.php?id=<?= (int) $resUser['id'] ?>" class="unit-resident-chip">
                                    <?= render_avatar($resUser, 'avatar-xs') ?>
                                    <span class="unit-resident-name"><?= e($resUser['full_name']) ?></span>
                                    <span class="tag"><?= $isOwner ? 'Daire Yöneticisi' : 'Sakin' ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Sağ: Daire Sakin Davet Kodu (DAIRE-XXXXX) -->
                    <?php if ($isUnitManager && !empty($currentUser['household_invite_code'])): ?>
                        <div class="unit-invite-box">
                            <div class="unit-invite-head">
                                <strong>Daireye Sakin Davet Kodu</strong>
                                <span>Aynı dairede yaşayan aile bireylerini veya ev arkadaşlarınızı bu kodla dairenize sakin olarak davet edebilirsiniz:</span>
                            </div>
                            <div class="unit-invite-actions">
                                <code><?= e($currentUser['household_invite_code']) ?></code>
                                <button type="button" class="btn-copy" data-copy="<?= e($currentUser['household_invite_code']) ?>">Kodu Kopyala</button>
                                <form method="post" action="apartment.php" style="margin-left: auto;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="regenerate_unit_code">
                                    <button type="submit" class="btn btn-outline btn-sm" title="Yeni kod üret">Yenile</button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="unit-invite-box">
                            <div class="unit-invite-head">
                                <strong>Daire Bilgisi</strong>
                                <span>Bu daireye sakin olarak bağlısınız. Daire su faturaları ve tüketim ortalaması tüm daire sakinleriyle ortaktır.</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- 3. Apartmandaki Daireler ve Su Tasarruf Sıralaması -->
            <section class="panel-card">
                <div class="panel-card-header">
                    <h2 class="section-title">Daireler ve Su Tüketim Tablosu</h2>
                    <form method="get" action="apartment.php" class="period-filter-form">
                        <select name="month">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= e(turkish_month_name($m)) ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="year">
                            <?php for ($y = (int) date('Y'); $y >= 2024; $y--): ?>
                                <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="btn btn-outline btn-sm">Göster</button>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Sıra</th>
                                <th>Daire</th>
                                <th>Daire Yöneticisi &amp; Sakinler</th>
                                <th>Kişi Sayısı</th>
                                <th>Aylık Sayaç (m³)</th>
                                <th>Kişi Başı Günlük</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($units as $idx => $unit): ?>
                                <?php
                                $isMyUnit  = (int) $unit['household_id'] === (int) $currentUser['household_id'];
                                $unitDaily = $unit['daily_liters_per_person'] !== null ? (float) $unit['daily_liters_per_person'] : null;
                                $isPioneer = ($unitDaily !== null && $avgDaily !== null && $unitDaily <= $avgDaily);
                                $ownerId   = (int) ($unit['owner_user_id'] ?? 0);
                                ?>
                                <tr class="<?= $isMyUnit ? 'row-highlight' : '' ?>">
                                    <td><?= $unitDaily !== null ? '#' . ($idx + 1) : '—' ?></td>
                                    <td><strong><?= e($unit['unit_label']) ?></strong></td>
                                    <td>
                                        <div class="residents-stack">
                                            <?php foreach ($unit['residents'] as $r): ?>
                                                <div class="resident-cell">
                                                    <a href="profile.php?id=<?= (int) $r['id'] ?>" class="author-link">
                                                        <strong><?= e($r['full_name']) ?></strong>
                                                    </a>
                                                    <?php if ($r['role'] === 'manager'): ?>
                                                        <span class="tag tag-manager">Apt. Yöneticisi</span>
                                                    <?php endif; ?>
                                                    <?php if ((int) $r['id'] === $ownerId): ?>
                                                        <span class="tag">Daire Yöneticisi</span>
                                                    <?php else: ?>
                                                        <span class="tag">Sakin</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td><?= (int) $unit['resident_count'] ?> kişi</td>
                                    <td>
                                        <?= $unit['consumption_m3'] !== null ? number_format((float) $unit['consumption_m3'], 1, ',', '.') . ' m³' : '—' ?>
                                    </td>
                                    <td>
                                        <?php if ($unitDaily !== null): ?>
                                            <strong><?= number_format($unitDaily, 1, ',', '.') ?> L / gün</strong>
                                        <?php else: ?>
                                            <span class="muted-text">Girilmedi</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($unitDaily === null): ?>
                                            <span class="metric-pill pill-neutral">Bekleniyor</span>
                                        <?php elseif ($isPioneer): ?>
                                            <span class="metric-pill pill-good">Tasarruf Öncüsü</span>
                                        <?php else: ?>
                                            <span class="metric-pill pill-neutral">Ortalama Üstü</span>
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
