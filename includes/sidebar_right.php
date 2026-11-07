<?php
/**
 * DEBI - Sağ Özet Sütunu (Günün Görevleri, Debi Göstergesi & Liderlik Tablosu)
 */
require_once __DIR__ . '/../modules/water/water_service.php';
require_once __DIR__ . '/../modules/community/community_service.php';

$quickWater   = $currentUser ? get_quick_water_summary($currentUser) : null;
$overview     = $currentUser ? get_neighborhood_overview((int) $currentUser['neighborhood_id'], (int) date('Y'), (int) date('n')) : null;
$dailyQuests  = $currentUser ? get_daily_quests_for_user((int) $currentUser['id']) : [];
$pointsLeader = $currentUser ? get_neighborhood_points_leaderboard((int) $currentUser['neighborhood_id'], 5) : [];
$currentUri   = basename($_SERVER['REQUEST_URI'] ?? 'index.php');
?>
<aside class="sidebar-right">
    <?php if ($currentUser): ?>
        <!-- 1. Günün Eğlenceli Su Görevleri Kartı -->
        <div class="panel-card quest-sidebar-card">
            <div class="panel-card-header">
                <span class="panel-title">🎯 Günün Su Görevleri</span>
                <span class="quest-reset-tag">Her gün yenilenir</span>
            </div>
            <p class="panel-desc">
                Bugün yaptığın tasarruf hareketlerini işaretle, anında <strong>Damla Puanı (DP)</strong> topla!
            </p>

            <div class="quest-mini-list">
                <?php foreach ($dailyQuests as $q): ?>
                    <?php $done = (int) $q['completed_today'] === 1; ?>
                    <div class="quest-mini-item <?= $done ? 'quest-done' : '' ?>">
                        <span class="quest-emoji"><?= $q['emoji'] ?></span>
                        <div class="quest-mini-body">
                            <strong><?= e($q['title']) ?></strong>
                            <span>~<?= (int) $q['water_saved_liters'] ?> L su kurtarır</span>
                        </div>
                        <?php if ($done): ?>
                            <span class="quest-done-pill">✓ +<?= (int) $q['points'] ?> DP</span>
                        <?php else: ?>
                            <form method="post" action="index.php" class="quest-inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="complete_quest">
                                <input type="hidden" name="quest_id" value="<?= (int) $q['id'] ?>">
                                <input type="hidden" name="return_url" value="<?= e($currentUri) ?>">
                                <button type="submit" class="btn-quest-claim" title="<?= e($q['description']) ?>">
                                    +<?= (int) $q['points'] ?> DP
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 2. Debi Su Göstergesi -->
        <?php if ($quickWater): ?>
            <div class="panel-card">
                <div class="panel-card-header">
                    <span class="panel-title">🌊 Debi Göstergesi</span>
                    <span class="panel-period"><?= e($quickWater['period_label']) ?></span>
                </div>

                <?php if ($quickWater['my_log']): ?>
                    <?php
                    $myDaily = (float) $quickWater['my_log']['daily_liters_per_person'];
                    $statusClass = $myDaily <= 110 ? 'status-good' : ($myDaily <= 150 ? 'status-warn' : 'status-high');
                    ?>
                    <div class="metric-highlight <?= $statusClass ?>">
                        <div class="metric-big"><?= number_format($myDaily, 1, ',', '.') ?> <small>L / kişi·gün</small></div>
                        <div class="metric-sub">
                            Bu ayki sayacınız: <strong><?= number_format((float) $quickWater['my_log']['consumption_m3'], 1, ',', '.') ?> m³</strong>
                            <?= $myDaily <= 100 ? ' · 🏆 100L Kulübü!' : '' ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-mini">
                        <p>Bu ayki su sayacını girip <strong>+100 DP</strong> (ve tasarrufluysan <strong>+150 DP bonus</strong>) kazan!</p>
                        <a href="water.php" class="btn btn-primary btn-sm btn-block">💧 Sayaç Gir (+100 DP)</a>
                    </div>
                <?php endif; ?>

                <div class="comparison-bars">
                    <div class="comp-item">
                        <div class="comp-row">
                            <span>🏘️ Mahalle Ortalaması</span>
                            <strong>
                                <?= $quickWater['neighborhood_avg'] !== null ? number_format($quickWater['neighborhood_avg'], 1, ',', '.') . ' L' : 'Veri yok' ?>
                            </strong>
                        </div>
                        <?php if ($quickWater['neighborhood_avg'] !== null): ?>
                            <?php $pctN = min(100, round(($quickWater['neighborhood_avg'] / 200) * 100)); ?>
                            <div class="progress-track">
                                <div class="progress-fill" style="width: <?= $pctN ?>%;"></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($currentUser['apartment_id'])): ?>
                        <div class="comp-item">
                            <div class="comp-row">
                                <span>🏢 Apartman Ortalaması</span>
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
                    <?php endif; ?>

                    <div class="comp-item">
                        <div class="comp-row">
                            <span>🎯 Tasarruf Hedefi</span>
                            <strong>100,0 L / gün</strong>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill fill-target" style="width: 50%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 3. Mahalle Puan (DP) Şampiyonları -->
        <div class="panel-card">
            <div class="panel-card-header">
                <span class="panel-title">🏆 Mahalle Puan Liderleri</span>
                <a href="neighborhood.php" class="link-subtle">Tümü</a>
            </div>
            <p class="panel-desc">
                <?= e($currentUser['neighborhood_name']) ?> içinde en çok Damla Puanı (DP) toplayan komşular:
            </p>

            <?php if (!empty($pointsLeader)): ?>
                <ol class="leaderboard-list">
                    <?php foreach ($pointsLeader as $idx => $row): ?>
                        <?php
                        $medal = $idx === 0 ? '🥇' : ($idx === 1 ? '🥈' : ($idx === 2 ? '🥉' : (string) ($idx + 1)));
                        $lvlInfo = get_level_info((int) $row['total_points']);
                        ?>
                        <li class="leaderboard-item <?= (int) $row['user_id'] === (int) $currentUser['id'] ? 'leaderboard-me' : '' ?>">
                            <span class="rank-num <?= $idx < 3 ? 'rank-medal' : '' ?>"><?= $medal ?></span>
                            <div class="rank-info">
                                <div class="rank-name">
                                    <?= e($row['full_name']) ?>
                                    <span title="<?= e($lvlInfo['title']) ?>"><?= $lvlInfo['emoji'] ?></span>
                                </div>
                                <div class="rank-sub">
                                    <?php if (!empty($row['apartment_name'])): ?>
                                        <?= e($row['apartment_name']) ?> · <?= e($row['unit_label']) ?>
                                    <?php else: ?>
                                        <?= e($row['unit_label']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="rank-val rank-dp">💧 <?= number_format((int) $row['total_points'], 0, ',', '.') ?> DP</span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>

        <!-- 4. Mahalle Su Tasarruf Sıralaması (Litre) -->
        <div class="panel-card">
            <div class="panel-card-header">
                <span class="panel-title">🌱 En Az Tüketen Haneler</span>
            </div>
            <?php if (!empty($overview['leaderboard'])): ?>
                <ol class="leaderboard-list">
                    <?php foreach ($overview['leaderboard'] as $idx => $row): ?>
                        <?php $medal = $idx === 0 ? '🥇' : ($idx === 1 ? '🥈' : ($idx === 2 ? '🥉' : (string) ($idx + 1))); ?>
                        <li class="leaderboard-item">
                            <span class="rank-num <?= $idx < 3 ? 'rank-medal' : '' ?>"><?= $medal ?></span>
                            <div class="rank-info">
                                <div class="rank-name"><?= e($row['full_name']) ?></div>
                                <div class="rank-sub">
                                    <?= !empty($row['apartment_name']) ? e($row['apartment_name'] . ' · ' . $row['unit_label']) : e($row['unit_label']) ?>
                                </div>
                            </div>
                            <span class="rank-val"><?= number_format((float) $row['daily_liters_per_person'], 1, ',', '.') ?> L</span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p class="muted-note">Bu ay henüz sayaç verisi girilmedi. İlk giren sen ol!</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</aside>
