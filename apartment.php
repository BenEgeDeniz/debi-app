<?php
/**
 * DEBI - Apartman Sayfası, Yönetici Davet Paneli ve Bireysel->Apartman Bağlantısı
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/community/community_service.php';

$currentUser = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Güvenlik doğrulaması başarısız oldu.');
        redirect('apartment.php');
    }

    $action = $_POST['action'] ?? '';

    // Bireysel kullanıcının davet koduyla apartmana katılması
    if ($action === 'join_apartment' && empty($currentUser['apartment_id'])) {
        $code  = $_POST['invite_code'] ?? '';
        $label = $_POST['unit_label'] ?? '';
        $res   = join_apartment_for_existing_user($currentUser, $code, $label);
        if ($res['ok']) {
            set_flash('success', 'Apartmanınıza başarıyla bağlandınız!');
        } else {
            set_flash('error', $res['error']);
        }
        redirect('apartment.php');
    }

    // Bireysel kullanıcının kendi mahallesinde apartman oluşturması
    if ($action === 'create_apartment' && empty($currentUser['apartment_id'])) {
        $name  = $_POST['apartment_name'] ?? '';
        $addr  = $_POST['address_detail'] ?? '';
        $units = (int) ($_POST['total_units'] ?? 12);
        $label = $_POST['unit_label'] ?? 'Daire 1';
        $res   = create_apartment_for_existing_user($currentUser, $name, $addr, $units, $label);
        if ($res['ok']) {
            set_flash('success', 'Apartmanınız oluşturuldu. Davet kodunu komşularınızla paylaşabilirsiniz.');
        } else {
            set_flash('error', $res['error']);
        }
        redirect('apartment.php');
    }

    // Apartman yöneticisinin daireye özel davet kodu üretmesi
    if ($action === 'create_unit_invite' && !empty($currentUser['apartment_id']) && $currentUser['role'] === 'manager') {
        $unitLabel = $_POST['unit_label'] ?? '';
        $res       = create_unit_invitation((int) $currentUser['apartment_id'], $unitLabel);
        if ($res['ok']) {
            set_flash('success', $unitLabel . ' için davet kodu üretildi: ' . $res['token']);
        } else {
            set_flash('error', $res['error']);
        }
        redirect('apartment.php');
    }

    // Apartman yöneticisinin aylık su hedefini güncellemesi
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

$pageTitle = !empty($currentUser['apartment_name'])
    ? $currentUser['apartment_name'] . ' — Apartmanım — Debi'
    : 'Apartman Bağlantısı — Debi';
$activeNav = 'apartment';
require_once __DIR__ . '/includes/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

    <main class="main-column">
        <?php if (empty($currentUser['apartment_id'])): ?>
            <!-- DURUM A: Kullanıcı mahallede bireysel / müstakil katılımcı -->
            <section class="panel-card page-hero-card">
                <div class="section-kicker">BİREYSEL / MÜSTAKİL HANE</div>
                <h1 class="page-title">Mahallede Bireysel Katılımcısınız</h1>
                <p class="page-subtitle">
                    Şu anda <strong><?= e($currentUser['neighborhood_name']) ?> (<?= e($currentUser['neighborhood_district'] . '/' . $currentUser['neighborhood_city']) ?>)</strong>
                    mahallesinde <strong><?= e(housing_type_label($currentUser['housing_type'])) ?></strong> olarak yer alıyorsunuz.
                    Dilerseniz bireysel olarak devam edebilir, dilerseniz aşağıdaki seçeneklerle bir apartmana dahil olabilirsiniz.
                </p>
            </section>

            <div class="grid-2-cols">
                <section class="panel-card">
                    <h2 class="section-title">1. Davet Kodu ile Apartmana Katıl</h2>
                    <p class="panel-desc">
                        Apartman yöneticiniz Debi'de apartman hesabı açtıysa, size ilettiği davet kodunu girerek mevcut hesabınızı ve geçmiş su kayıtlarınızı kaybetmeden dairenize bağlanabilirsiniz.
                    </p>
                    <form method="post" action="apartment.php" class="form-stack">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="join_apartment">

                        <div class="form-group">
                            <label for="invite_code">Apartman veya Daire Davet Kodu</label>
                            <input type="text" id="invite_code" name="invite_code" required placeholder="Örn: DEBI-7K4M2 veya DAIRE-9P2X4">
                        </div>

                        <div class="form-group">
                            <label for="join_unit_label">Daire Numaranız</label>
                            <input type="text" id="join_unit_label" name="unit_label" required placeholder="Örn: Daire 4">
                        </div>

                        <button type="submit" class="btn btn-primary">Apartmana Bağlan</button>
                    </form>
                </section>

                <section class="panel-card">
                    <h2 class="section-title">2. Apartmanınızı Siz Oluşturun</h2>
                    <p class="panel-desc">
                        <strong><?= e($currentUser['neighborhood_name']) ?></strong> mahallesindeki apartmanınızı kaydedip yönetici olabilir ve komşularınıza daire davet kodu gönderebilirsiniz.
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
            $apt         = $aptData['apartment'];
            $units       = $aptData['units'];
            $stats       = $aptData['stats'];
            $invitations = $aptData['invitations'];
            $avgDaily    = $stats['avg_daily_liters'] !== null ? (float) $stats['avg_daily_liters'] : null;
            ?>
            <section class="panel-card page-hero-card">
                <div class="page-hero-top">
                    <div>
                        <div class="section-kicker">
                            <a href="neighborhood.php"><?= e($apt['neighborhood_name']) ?></a> &rsaquo; APARTMAN TOPLULUĞU
                        </div>
                        <h1 class="page-title"><?= e($apt['name']) ?></h1>
                        <p class="page-subtitle">
                            <?= e($apt['district'] . ' / ' . $apt['city']) ?>
                            <?= !empty($apt['address_detail']) ? ' · ' . e($apt['address_detail']) : '' ?>
                            · Yönetici: <strong><?= e($apt['manager_name'] ?? 'Belirtilmedi') ?></strong>
                        </p>
                    </div>

                    <div class="invite-badge-card">
                        <span class="invite-badge-title">Genel Apartman Davet Kodu</span>
                        <div class="invite-badge-code">
                            <code><?= e($apt['invite_code']) ?></code>
                            <button type="button" class="btn-copy" data-copy="<?= e($apt['invite_code']) ?>">Kopyala</button>
                        </div>
                        <span class="invite-badge-hint">Komşularınız kayıt olurken bu kodu girebilir</span>
                    </div>
                </div>

                <div class="stats-grid-4">
                    <div class="stat-box">
                        <span class="stat-label">Kayıtlı Daireler</span>
                        <strong class="stat-value"><?= count($units) ?> / <?= (int) $apt['total_units'] ?></strong>
                    </div>
                    <div class="stat-box">
                        <span class="stat-label">Veri Giren Daire</span>
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
                        <span class="stat-label">Apartman Aylık Toplam</span>
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
            </section>

            <?php if ($currentUser['role'] === 'manager'): ?>
                <!-- Apartman Yöneticisi Paneli: Daire Daveti Üretme & Hedef Belirleme -->
                <section class="panel-card manager-panel">
                    <div class="panel-card-header">
                        <h2 class="section-title">Apartman Yöneticisi Paneli — Daire Daveti &amp; Hedef</h2>
                        <span class="tag tag-manager">Yönetici Yetkisi</span>
                    </div>

                    <div class="grid-2-cols">
                        <div>
                            <h3 class="sub-heading">Daireye Özel Davet Kodu Oluştur</h3>
                            <p class="panel-desc">
                                Genel apartman koduna (<code><?= e($apt['invite_code']) ?></code>) ek olarak, belirli bir daire için tek kullanımlık davet anahtarı üretebilirsiniz:
                            </p>
                            <form method="post" action="apartment.php" class="inline-action-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="create_unit_invite">
                                <input type="text" name="unit_label" required placeholder="Örn: Daire 6">
                                <button type="submit" class="btn btn-primary">Davet Üret</button>
                            </form>
                        </div>

                        <div>
                            <h3 class="sub-heading">Apartman Aylık Toplam Su Hedefi (m³)</h3>
                            <p class="panel-desc">
                                Apartmandaki tüm dairelerin toplam aylık tüketimi için hedef eşik belirleyin:
                            </p>
                            <form method="post" action="apartment.php" class="inline-action-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_goal">
                                <input type="number" step="0.5" min="5" max="10000" name="monthly_goal_m3"
                                       value="<?= e((string) ($apt['monthly_goal_m3'] ?? '')) ?>" placeholder="Örn: 120">
                                <button type="submit" class="btn btn-outline">Hedefi Kaydet</button>
                            </form>
                        </div>
                    </div>

                    <?php if (!empty($invitations)): ?>
                        <div class="invitations-table-wrap">
                            <h3 class="sub-heading">Üretilen Daire Davet Kodları</h3>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Daire</th>
                                            <th>Davet Kodu</th>
                                            <th>Durum</th>
                                            <th>Kayıt Bağlantısı / İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invitations as $inv): ?>
                                            <tr>
                                                <td><strong><?= e($inv['unit_label']) ?></strong></td>
                                                <td><code><?= e($inv['invite_token']) ?></code></td>
                                                <td>
                                                    <?php if ((int) $inv['is_used'] === 1): ?>
                                                        <span class="metric-pill pill-good">Katıldı (<?= e($inv['used_by_name'] ?? 'Üye') ?>)</span>
                                                    <?php else: ?>
                                                        <span class="metric-pill pill-neutral">Bekliyor</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ((int) $inv['is_used'] === 0): ?>
                                                        <button type="button" class="btn-copy" data-copy="<?= e($inv['invite_token']) ?>">Kodu Kopyala</button>
                                                        <a href="register.php?mode=apartment_invite&amp;code=<?= urlencode($inv['invite_token']) ?>" class="link-subtle">Davet Linki</a>
                                                    <?php else: ?>
                                                        <span class="muted-text">Tamamlandı</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <!-- Apartmandaki Daireler ve Su Tasarruf Sıralaması -->
            <section class="panel-card">
                <div class="panel-card-header">
                    <h2 class="section-title">Daireler ve Su Tüketim Tablosu (<?= e(turkish_month_name($month) . ' ' . $year) ?>)</h2>
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
                                <th>Sakin</th>
                                <th>Kişi Sayısı</th>
                                <th>Aylık Sayaç (m³)</th>
                                <th>Kişi Başı Günlük</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($units as $idx => $unit): ?>
                                <?php
                                $isMe      = (int) $unit['user_id'] === (int) $currentUser['id'];
                                $unitDaily = $unit['daily_liters_per_person'] !== null ? (float) $unit['daily_liters_per_person'] : null;
                                $isPioneer = ($unitDaily !== null && $avgDaily !== null && $unitDaily <= $avgDaily);
                                ?>
                                <tr class="<?= $isMe ? 'row-highlight' : '' ?>">
                                    <td><?= $unitDaily !== null ? '#' . ($idx + 1) : '—' ?></td>
                                    <td><strong><?= e($unit['unit_label']) ?></strong></td>
                                    <td>
                                        <?= e($unit['full_name']) ?>
                                        <?php if ($unit['role'] === 'manager'): ?>
                                            <span class="tag tag-manager">Yönetici</span>
                                        <?php endif; ?>
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
                                            <span class="muted-text">Bekleniyor</span>
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

    <?php require_once __DIR__ . '/includes/sidebar_right.php'; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

