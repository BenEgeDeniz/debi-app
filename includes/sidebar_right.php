<?php
/**
 * DEBI - Sağ Özet Sütunu (Fatura Özeti & Arkadaşlar / Apartman Sıralaması)
 */
require_once __DIR__ . '/../modules/water/water_service.php';

$quickWater   = $currentUser ? get_quick_water_summary($currentUser) : null;
$leaders      = $currentUser ? get_friends_and_apartment_leaderboard($currentUser, 6) : [];
$incomingReqs = $currentUser ? get_incoming_friend_requests((int) $currentUser['id']) : [];
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
                Su Analizi &amp; Debi Göleti &rarr;
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
