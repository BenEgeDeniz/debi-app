<?php
/**
 * DEBI - Sağ Özet Sütunu (Debi Puanlı Daire Göleti & 3'lü Puan Sıralaması: Arkadaş / Apartman / Genel)
 */
require_once __DIR__ . '/../modules/water/water_service.php';
require_once __DIR__ . '/../modules/community/community_service.php';

$quickWater    = $currentUser ? get_quick_water_summary($currentUser) : null;
$pondAnalytics = $currentUser ? get_household_water_analytics($currentUser) : null;
$incomingReqs  = $currentUser ? get_incoming_friend_requests((int) $currentUser['id']) : [];

$friendLeaders = $currentUser ? get_friends_drops_leaderboard($currentUser, 6) : [];
$aptLeaders    = (!empty($currentUser['apartment_id'])) ? get_apartment_drops_leaderboard($currentUser, 6) : [];
$genLeaders    = $currentUser ? get_general_apartments_drops_leaderboard(6) : [];
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

        <!-- 2. Debi Puan Sıralaması: Arkadaş / Apartman / Genel -->
        <div class="panel-card sidebar-leaderboard-card">
            <div class="panel-card-header" style="margin-bottom: 8px;">
                <span class="panel-title">🏆 Puan Sıralaması</span>
            </div>

            <!-- 3'lü Sekme Çubuğu -->
            <div class="lb-switch-tabs" role="tablist">
                <button type="button" class="lb-switch-tab js-lb-tab active" data-target="lbFriends">
                    Arkadaş
                </button>
                <?php if (!empty($currentUser['apartment_id'])): ?>
                    <button type="button" class="lb-switch-tab js-lb-tab" data-target="lbApartment">
                        Apartman
                    </button>
                <?php endif; ?>
                <button type="button" class="lb-switch-tab js-lb-tab" data-target="lbGeneral">
                    Genel
                </button>
            </div>

            <!-- 1. Arkadaş Daire Puanları -->
            <div class="lb-panel js-lb-panel" id="lbFriends">
                <?php if (!empty($friendLeaders)): ?>
                    <ol class="leaderboard-list">
                        <?php foreach ($friendLeaders as $idx => $row): ?>
                            <?php $isMe = (int) $row['user_id'] === (int) $currentUser['id']; ?>
                            <li class="leaderboard-item <?= $isMe ? 'leaderboard-me' : '' ?>">
                                <span class="rank-num"><?= $idx + 1 ?></span>
                                <div class="rank-info">
                                    <div class="rank-name">
                                        <a href="profile.php?id=<?= (int) $row['user_id'] ?>" class="author-link">
                                            <?= e($row['full_name']) ?><?= $isMe ? ' <small style="opacity:0.75;">(Sen)</small>' : '' ?>
                                        </a>
                                    </div>
                                    <div class="rank-sub">
                                        <?= !empty($row['apartment_name']) ? e($row['apartment_name'] . ' · ' . $row['unit_label']) : e($row['unit_label']) ?>
                                    </div>
                                </div>
                                <span class="rank-val" title="Daire Toplam Puanı">💧 <?= number_format((int) $row['total_drops'], 0, ',', '.') ?> Puan</span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <p class="muted-note">Henüz ekli arkadaşınız yok.</p>
                <?php endif; ?>
            </div>

            <!-- 2. Apartman Daire Puanları -->
            <?php if (!empty($currentUser['apartment_id'])): ?>
                <div class="lb-panel js-lb-panel" id="lbApartment" style="display: none;">
                    <?php if (!empty($aptLeaders)): ?>
                        <ol class="leaderboard-list">
                            <?php foreach ($aptLeaders as $idx => $row): ?>
                                <?php $isMyUnit = (int) $row['household_id'] === (int) $currentUser['household_id']; ?>
                                <li class="leaderboard-item <?= $isMyUnit ? 'leaderboard-me' : '' ?>">
                                    <span class="rank-num"><?= $idx + 1 ?></span>
                                    <div class="rank-info">
                                        <div class="rank-name">
                                            <strong><?= e(format_unit_label($row['unit_label'], $row['housing_type'])) ?></strong>
                                            <?= $isMyUnit ? ' <small style="opacity:0.75;">(Daireniz)</small>' : '' ?>
                                        </div>
                                        <div class="rank-sub">
                                            <?= e($row['display_name']) ?>
                                        </div>
                                    </div>
                                    <span class="rank-val" title="Daire Toplam Puanı">💧 <?= number_format((int) $row['total_drops'], 0, ',', '.') ?> Puan</span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else: ?>
                        <p class="muted-note">Apartmanda henüz puanlı daire yok.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- 3. Genel Apartman Puan Toplamları Sıralaması -->
            <div class="lb-panel js-lb-panel" id="lbGeneral" style="display: none;">
                <?php if (!empty($genLeaders)): ?>
                    <ol class="leaderboard-list">
                        <?php foreach ($genLeaders as $idx => $row): ?>
                            <?php $isMyApt = !empty($currentUser['apartment_id']) && (int) $row['apartment_id'] === (int) $currentUser['apartment_id']; ?>
                            <li class="leaderboard-item <?= $isMyApt ? 'leaderboard-me' : '' ?>">
                                <span class="rank-num"><?= $idx + 1 ?></span>
                                <div class="rank-info">
                                    <div class="rank-name">
                                        <strong><?= e($row['apartment_name']) ?></strong>
                                        <?= $isMyApt ? ' <small style="opacity:0.75;">(Apartmanınız)</small>' : '' ?>
                                    </div>
                                    <div class="rank-sub">
                                        <?= e($row['district'] . ' / ' . $row['city']) ?> &middot; <?= (int) $row['daire_count'] ?> Daire
                                    </div>
                                </div>
                                <span class="rank-val" title="Apartman Toplam Daire Puanları">💧 <?= number_format((int) $row['total_drops'], 0, ',', '.') ?> Puan</span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <p class="muted-note">Henüz apartman kaydı yok.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</aside>
