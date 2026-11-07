<?php
/**
 * DEBI - Sağ Özet Sütunu (Fatura Özeti & Arkadaşlar / Apartman Sıralaması)
 */
require_once __DIR__ . '/../modules/water/water_service.php';

$quickWater    = $currentUser ? get_quick_water_summary($currentUser) : null;
$pondAnalytics = $currentUser ? get_household_water_analytics($currentUser) : null;
$leaders       = $currentUser ? get_friends_and_apartment_leaderboard($currentUser, 6) : [];
$incomingReqs  = $currentUser ? get_incoming_friend_requests((int) $currentUser['id']) : [];
?>
<aside class="sidebar-right">
    <?php if ($currentUser && $quickWater): ?>
        <?php if (!empty($incomingReqs)): ?>
            <div class="panel-card">
                <div class="panel-card-header">
                    <span class="panel-title">Arkadaşlık İstekleri (<?= count($incomingReqs) ?>)</span>
                    <a href="friends.php" class="link-subtle">Tümü</a>
                </div>
                <p class="panel-desc">Size arkadaşlık isteği gönderen kullanıcılar var.</p>
                <a href="friends.php" class="btn btn-primary btn-sm btn-block">İstekleri Görüntüle</a>
            </div>
        <?php endif; ?>

        <!-- 0. Daire Göleti & Damla Motivasyon Kartı (Daire Bazlı Gerçek Gölet) -->
        <?php if ($pondAnalytics): ?>
            <?php
            $sidePondData  = $pondAnalytics['household_pond'] ?? [
                'total_drops'     => 0,
                'target_drops'    => 100,
                'saved_m3'        => 0.0,
                'saved_liters'    => 0,
                'doluluk_yuzdesi' => 0.0,
                'stage'           => 'dry',
                'stage_title'     => 'Kurumuş Gölet (Başlangıç)',
                'next_stage_hint' => 'Yarı Dolu Gölet aşamasına 30 Damla 💧 kaldı',
            ];
            $sidePondStage = $sidePondData['stage'] ?? 'dry';
            $sidePondImg   = brand_asset_path('pond_' . $sidePondStage);
            $sideDrops     = (int) ($sidePondData['total_drops'] ?? 0);
            $sideTarget    = (int) ($sidePondData['target_drops'] ?? 100);
            $sidePct       = (float) ($sidePondData['doluluk_yuzdesi'] ?? 0.0);
            $sideSavedLit  = (int) ($sidePondData['saved_liters'] ?? 0);
            $sideUnitName  = format_unit_label($currentUser['unit_label'] ?? '', $currentUser['housing_type'] ?? 'apartment_unit');
            ?>
            <div class="panel-card sidebar-pond-card" id="daireGoleti">
                <div class="panel-card-header">
                    <div>
                        <span class="panel-title">Daire Göletimiz</span>
                        <div style="font-size: 0.76rem; font-weight: 700; color: var(--text-muted);">
                            <?= e($sideUnitName) ?> &middot; <?= e($sidePondData['stage_title'] ?? '') ?>
                        </div>
                    </div>
                    <span class="drop-motivation-badge">
                        <span class="drop-badge-icon">💧</span>
                        <strong><?= number_format($sideDrops, 0, ',', '.') ?> Damla</strong>
                    </span>
                </div>

                <!-- Motivasyon Damla Gösterge Kutusu -->
                <div class="sidebar-drop-hero">
                    <div class="sidebar-drop-hero-main">
                        <span class="sidebar-drop-big">💧 <?= number_format($sideDrops, 0, ',', '.') ?> <small>/ <?= $sideTarget ?> Damla</small></span>
                        <span class="sidebar-drop-sub"><?= e($sidePondData['next_stage_hint'] ?? '') ?></span>
                    </div>
                </div>

                <div class="sidebar-pond-visual">
                    <img src="<?= e($sidePondImg) ?>" alt="Daire Su Göleti" class="sidebar-pond-img">
                </div>

                <div class="sidebar-pond-footer">
                    <div class="comp-row" style="margin-top: 10px; margin-bottom: 5px;">
                        <span>Gölet Doluluğu (%<?= number_format($sidePct, 0, ',', '.') ?>)</span>
                        <strong>
                            <?= $sideSavedLit > 0 ? number_format($sideSavedLit, 0, ',', '.') . ' L Su Kurtarıldı' : $sideDrops . ' / ' . $sideTarget . ' Damla' ?>
                        </strong>
                    </div>
                    <div class="progress-track" style="height: 10px;">
                        <div class="progress-fill fill-green" style="width: <?= min(100, max(3, $sidePct)) ?>%;"></div>
                    </div>

                    <a href="water.php" class="btn btn-primary btn-sm btn-block" style="margin-top: 12px;">
                        📷 Fatura Yükle &amp; Damla Kazan
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- 1. Bu Ayki Fatura & Debi Özeti -->
        <div class="panel-card">
            <div class="panel-card-header">
                <span class="panel-title">Fatura &amp; Debi Özeti</span>
                <span class="panel-period"><?= e($quickWater['period_label']) ?></span>
            </div>

            <?php if ($quickWater['my_log']): ?>
                <?php
                $myDaily     = (float) $quickWater['my_log']['daily_liters_per_person'];
                $statusClass = $myDaily <= 110 ? 'status-good' : ($myDaily <= 150 ? 'status-warn' : 'status-high');
                $mascotPose  = $myDaily <= 110 ? 'happy' : ($myDaily <= 150 ? 'thumbsup' : 'thinking');
                ?>
                <div class="metric-highlight metric-with-mascot <?= $statusClass ?>">
                    <?= render_mascot($mascotPose, 'mascot-sm', 'Su Durumu Maskotu') ?>
                    <div>
                        <div class="metric-big"><?= number_format($myDaily, 1, ',', '.') ?> <small>L / kişi·gün</small></div>
                        <div class="metric-sub">
                            Bu ayki faturanız: <strong><?= number_format((float) $quickWater['my_log']['consumption_m3'], 1, ',', '.') ?> m³</strong>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-mini">
                    <div class="empty-mini-mascot">
                        <?= render_mascot('thinking', 'mascot-sm', 'Debi Düşünen Maskot') ?>
                    </div>
                    <p>Bu ayın su faturasını henüz yüklemediniz. Fotoğrafını yükleyerek yapay zeka ile okutabilirsiniz.</p>
                    <a href="water.php" class="btn btn-primary btn-sm btn-block">Fatura Fotoğrafı Yükle</a>
                </div>
            <?php endif; ?>

            <?php if (!empty($currentUser['apartment_id'])): ?>
                <div class="comparison-bars">
                    <div class="comp-item">
                        <div class="comp-row">
                            <span>Apartman Ortalaması</span>
                            <strong>
                                <?= $quickWater['apartment_avg'] !== null ? number_format($quickWater['apartment_avg'], 1, ',', '.') . ' L' : 'Veri yok' ?>
                            </strong>
                        </div>
                        <?php if ($quickWater['apartment_avg'] !== null): ?>
                            <?php $pctA = min(100, round(($quickWater['apartment_avg'] / 200) * 100)); ?>
                            <div class="progress-track">
                                <div class="progress-fill fill-green" style="width: <?= $pctA ?>%;"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <a href="analytics.php" class="btn btn-outline btn-sm btn-block" style="margin-top: 10px;">
                Su Tüketim Analizi (m³ Grafiği) &rarr;
            </a>
        </div>

        <!-- 2. Arkadaşlar & Apartman En Az Tüketenler -->
        <div class="panel-card">
            <div class="panel-card-header">
                <span class="panel-title">Çevremde En Az Tüketenler</span>
                <a href="friends.php" class="link-subtle">Arkadaşlar</a>
            </div>
            <?php if (!empty($leaders)): ?>
                <ol class="leaderboard-list">
                    <?php foreach ($leaders as $idx => $row): ?>
                        <li class="leaderboard-item <?= (int) $row['user_id'] === (int) $currentUser['id'] ? 'leaderboard-me' : '' ?>">
                            <span class="rank-num"><?= $idx + 1 ?></span>
                            <div class="rank-info">
                                <div class="rank-name">
                                    <a href="profile.php?id=<?= (int) $row['user_id'] ?>" class="author-link">
                                        <?= e($row['full_name']) ?>
                                    </a>
                                </div>
                                <div class="rank-sub">
                                    <?= !empty($row['apartment_name']) ? e($row['apartment_name'] . ' · ' . $row['unit_label']) : e($row['unit_label']) ?>
                                </div>
                            </div>
                            <span class="rank-val"><?= number_format((float) $row['daily_liters_per_person'], 1, ',', '.') ?> L</span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p class="muted-note">Arkadaş çevrenizde bu ay henüz fatura verisi girilmedi.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</aside>
