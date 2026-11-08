<?php
/**
 * DEBI - Sağ Özet Sütunu (Debi Puanlı Daire Göleti & 3'lü Puan Sıralaması: Arkadaş / Apartman / Genel)
 */
require_once __DIR__ . '/../modules/water/water_service.php';
require_once __DIR__ . '/../modules/community/community_service.php';
require_once __DIR__ . '/../modules/gamification/gamification_service.php';

$quickWater    = $currentUser ? get_quick_water_summary($currentUser) : null;
$pondAnalytics = $currentUser ? get_household_water_analytics($currentUser) : null;
$incomingReqs  = $currentUser ? get_incoming_friend_requests((int) $currentUser['id']) : [];
$pointBreakdown = $currentUser ? get_household_point_breakdown(
    (int) ($currentUser['household_id'] ?? 0),
    (int) ($currentUser['id'] ?? 0)
) : [
    'total' => 0,
    'user' => 0,
    'household' => 0,
];

?>
<aside class="sidebar-right">
    <?php if ($currentUser && $quickWater): ?>
        <?php if (!empty($incomingReqs)): ?>
            <div class="panel-card">
                <div class="panel-card-header" style="margin-bottom: 8px;">
                    <span class="panel-title">👋 <?= count($incomingReqs) ?> İstek</span>
                </div>
                <a href="friends.php" class="btn btn-primary btn-sm btn-block">İstekleri Gör</a>
            </div>
        <?php endif; ?>

        <!-- 0. Debi Puanlı Daire Göleti Kartı -->
        <?php if ($pondAnalytics): ?>
            <?php
            $sidePondData  = $pondAnalytics['household_pond'] ?? [
                'total_drops'     => 0,
                'target_drops'    => 100,
                'saved_m3'        => 0.0,
                'saved_liters'    => 0,
                'doluluk_yuzdesi' => 0.0,
                'stage'           => 'dry',
                'stage_title'     => 'Kurumuş',
                'next_stage_hint' => 'Yarı doluya 30 💧 kaldı',
            ];
            $sidePondStage = $sidePondData['stage'] ?? 'dry';
            $sidePondImg   = brand_asset_path('pond_' . $sidePondStage);
            $sidePct       = (float) ($sidePondData['doluluk_yuzdesi'] ?? 0.0);
            $sideUnitName  = format_unit_label($currentUser['unit_label'] ?? '', $currentUser['housing_type'] ?? 'apartment_unit');
            ?>
            <div class="panel-card sidebar-pond-card" id="daireGoleti">
                <div class="panel-card-header">
                    <div>
                        <span class="panel-title">Debi Puanı Göleti</span>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted);">
                            <?= e($sideUnitName) ?> &middot; <?= e($sidePondData['stage_title'] ?? '') ?>
                        </div>
                    </div>
                </div>

                <div class="sidebar-pond-visual">
                    <img src="<?= e($sidePondImg) ?>" alt="Debi Puanı Daire Göleti" class="sidebar-pond-img">
                </div>

                <div class="sidebar-pond-footer">
                    <div class="comp-row" style="margin-top: 8px; margin-bottom: 5px; font-size: 0.78rem;">
                        <span><?= e($sidePondData['next_stage_hint'] ?? '') ?></span>
                        <strong>%<?= number_format($sidePct, 0, ',', '.') ?></strong>
                    </div>
                    <div class="progress-track" style="height: 9px;">
                        <div class="progress-fill fill-green" style="width: <?= min(100, max(3, $sidePct)) ?>%;"></div>
                    </div>
                    <div class="pond-points-summary">
                        <strong>Şimdiye kadar kazanılan toplam DP</strong>
                        <strong><?= number_format($pointBreakdown['total'], 0, ',', '.') ?> DP</strong>
                        <div class="pond-points-breakdown">
                            <span>Siz: <?= number_format($pointBreakdown['user'], 0, ',', '.') ?> DP</span>
                            <span>Hane katkısı: <?= number_format($pointBreakdown['household'], 0, ',', '.') ?> DP</span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 1. Bu Ayki Fatura & Debi Özeti -->
        <div class="panel-card">
            <div class="panel-card-header">
                <span class="panel-title">Bu Ay</span>
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
                        <div class="metric-big"><?= number_format($myDaily, 1, ',', '.') ?> <small>L / gün</small></div>
                        <div class="metric-sub">
                            <strong><?= number_format((float) $quickWater['my_log']['consumption_m3'], 1, ',', '.') ?> m³</strong>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-mini">
                    <div class="empty-mini-mascot">
                        <?= render_mascot('thinking', 'mascot-sm', 'Debi Maskotu') ?>
                    </div>
                    <p>Bu ay fatura girilmedi.</p>
                    <a href="water.php" class="btn btn-primary btn-sm btn-block">+ Fatura Ekle</a>
                </div>
            <?php endif; ?>

            <?php if (!empty($currentUser['apartment_id'])): ?>
                <div class="comparison-bars">
                    <div class="comp-item">
                        <div class="comp-row">
                            <span>Apartman Ort.</span>
                            <strong>
                                <?= $quickWater['apartment_avg'] !== null ? number_format($quickWater['apartment_avg'], 1, ',', '.') . ' L' : '—' ?>
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
        </div>

    <?php endif; ?>
</aside>
