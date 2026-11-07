<?php
/**
 * DEBI - Mahalle Sayfası (Apartmanlar + Bireysel/Müstakil Haneler)
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/community/community_service.php';

$currentUser = require_login();

$year  = isset($_GET['year']) ? max(2020, min(2035, (int) $_GET['year'])) : (int) date('Y');
$month = isset($_GET['month']) ? max(1, min(12, (int) $_GET['month'])) : (int) date('n');

$data         = get_neighborhood_overview((int) $currentUser['neighborhood_id'], $year, $month);
$neighborhood = $data['neighborhood'];
$apartments   = $data['apartments'];
$individuals  = $data['individual_households'];
$stats        = $data['stats'];

$pageTitle = $neighborhood['name'] . ' — Mahalle Ağı — Debi';
$activeNav = 'neighborhood';
require_once __DIR__ . '/includes/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

    <main class="main-column">
        <section class="panel-card page-hero-card">
            <div class="page-hero-top">
                <div>
                    <div class="section-kicker">MAHALLE ÇATISI</div>
                    <h1 class="page-title"><?= e($neighborhood['name']) ?></h1>
                    <p class="page-subtitle">
                        <?= e($neighborhood['district'] . ' / ' . $neighborhood['city']) ?> — Mahalledeki tüm apartmanlar, müstakil evler ve bireysel katılımcılar
                    </p>
                </div>

                <form method="get" action="neighborhood.php" class="period-filter-form">
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

            <div class="stats-grid-4">
                <div class="stat-box">
                    <span class="stat-label">Kayıtlı Apartman</span>
                    <strong class="stat-value"><?= (int) ($stats['total_apartments'] ?? 0) ?></strong>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Toplam Hane (Daire + Bireysel)</span>
                    <strong class="stat-value"><?= (int) ($stats['total_households'] ?? 0) ?></strong>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Mahalle Ortalaması</span>
                    <strong class="stat-value">
                        <?= $stats['avg_daily_liters'] !== null ? number_format((float) $stats['avg_daily_liters'], 1, ',', '.') . ' L' : '—' ?>
                    </strong>
                    <span class="stat-sub">kişi başı / gün</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Dönem Toplam Sayaç</span>
                    <strong class="stat-value">
                        <?= $stats['total_m3'] !== null ? number_format((float) $stats['total_m3'], 1, ',', '.') . ' m³' : '0 m³' ?>
                    </strong>
                    <span class="stat-sub"><?= e(turkish_month_name($month) . ' ' . $year) ?></span>
                </div>
            </div>
        </section>

        <!-- Mahalledeki Apartmanlar -->
        <section class="panel-card">
            <div class="panel-card-header">
                <h2 class="section-title">Mahalledeki Apartmanlar (<?= count($apartments) ?>)</h2>
                <?php if (empty($currentUser['apartment_id'])): ?>
                    <a href="apartment.php" class="btn btn-outline btn-sm">Apartman Ekle / Katıl</a>
                <?php endif; ?>
            </div>
            <p class="panel-desc">
                <?= e($neighborhood['name']) ?> sınırları içinde Debi'ye katılan apartmanların <?= e(turkish_month_name($month) . ' ' . $year) ?> dönemi su tasarruf performansı:
            </p>

            <?php if (empty($apartments)): ?>
                <div class="empty-inline">
                    Bu mahallede henüz kayıtlı bir apartman bulunmuyor. İlk apartmanı oluşturabilir veya bireysel olarak devam edebilirsiniz.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Sıra</th>
                                <th>Apartman Adı</th>
                                <th>Yönetici</th>
                                <th>Katılan Daire</th>
                                <th>Aylık Toplam</th>
                                <th>Kişi Başı Günlük</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($apartments as $idx => $apt): ?>
                                <?php $isMyApt = !empty($currentUser['apartment_id']) && (int) $currentUser['apartment_id'] === (int) $apt['id']; ?>
                                <tr class="<?= $isMyApt ? 'row-highlight' : '' ?>">
                                    <td>#<?= $idx + 1 ?></td>
                                    <td>
                                        <strong><?= e($apt['name']) ?></strong>
                                        <?php if ($isMyApt): ?>
                                            <span class="tag tag-manager">Sizin Apartmanınız</span>
                                        <?php endif; ?>
                                        <?php if (!empty($apt['address_detail'])): ?>
                                            <div class="table-sub"><?= e($apt['address_detail']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($apt['manager_name'] ?? '—') ?></td>
                                    <td><?= (int) $apt['registered_households'] ?> / <?= (int) $apt['total_units'] ?> daire</td>
                                    <td>
                                        <?= $apt['total_m3'] !== null ? number_format((float) $apt['total_m3'], 1, ',', '.') . ' m³' : '—' ?>
                                    </td>
                                    <td>
                                        <?php if ($apt['avg_daily_liters'] !== null): ?>
                                            <span class="metric-pill <?= (float) $apt['avg_daily_liters'] <= 110 ? 'pill-good' : 'pill-neutral' ?>">
                                                <?= number_format((float) $apt['avg_daily_liters'], 1, ',', '.') ?> L / gün
                                            </span>
                                        <?php else: ?>
                                            <span class="muted-text">Veri girilmedi</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- Mahalledeki Bireysel & Müstakil Katılımcılar -->
        <section class="panel-card">
            <div class="panel-card-header">
                <h2 class="section-title">Bireysel &amp; Müstakil Katılımcılar (<?= count($individuals) ?>)</h2>
            </div>
            <p class="panel-desc">
                Apartmanı henüz katılmadığı için mahallede bireysel olarak yer alan daireler ve müstakil ev haneleri:
            </p>

            <?php if (empty($individuals)): ?>
                <div class="empty-inline">
                    Bu mahallede şu an bireysel veya müstakil kayıtlı hane bulunmuyor.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Katılımcı</th>
                                <th>Damla Puanı</th>
                                <th>Konut Tipi</th>
                                <th>Birim / Hane Tanımı</th>
                                <th>Kişi Sayısı</th>
                                <th>Aylık Tüketim</th>
                                <th>Kişi Başı Günlük</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($individuals as $ind): ?>
                                <?php
                                $isMe = (int) $ind['user_id'] === (int) $currentUser['id'];
                                $indPts = (int) ($ind['total_points'] ?? 50);
                                $indLvl = get_level_info($indPts);
                                ?>
                                <tr class="<?= $isMe ? 'row-highlight' : '' ?>">
                                    <td>
                                        <strong><?= e($ind['full_name']) ?></strong>
                                        <?php if ($isMe): ?>
                                            <span class="tag tag-manager">Siz</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="author-lvl-chip"><?= $indLvl['emoji'] ?> <?= number_format($indPts, 0, ',', '.') ?> DP</span>
                                    </td>
                                    <td><?= e(housing_type_label($ind['housing_type'])) ?></td>
                                    <td><?= e($ind['unit_label']) ?></td>
                                    <td><?= (int) $ind['resident_count'] ?> kişi</td>
                                    <td>
                                        <?= $ind['consumption_m3'] !== null ? number_format((float) $ind['consumption_m3'], 1, ',', '.') . ' m³' : '—' ?>
                                    </td>
                                    <td>
                                        <?php if ($ind['daily_liters_per_person'] !== null): ?>
                                            <span class="metric-pill <?= (float) $ind['daily_liters_per_person'] <= 110 ? 'pill-good' : 'pill-neutral' ?>">
                                                <?= number_format((float) $ind['daily_liters_per_person'], 1, ',', '.') ?> L / gün
                                            </span>
                                        <?php else: ?>
                                            <span class="muted-text">Veri girilmedi</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/sidebar_right.php'; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

