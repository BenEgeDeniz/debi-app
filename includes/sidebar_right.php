<?php
/**
 * DEBI - Sağ Özet Sütunu (Mahalle & Apartman Su Kıyaslaması ve Sıralama)
 */
require_once __DIR__ . '/../modules/water/water_service.php';
require_once __DIR__ . '/../modules/community/community_service.php';

$quickWater = $currentUser ? get_quick_water_summary($currentUser) : null;
$overview   = $currentUser ? get_neighborhood_overview((int) $currentUser['neighborhood_id'], (int) date('Y'), (int) date('n')) : null;
?>
<aside class="sidebar-right">
    <?php if ($currentUser && $quickWater): ?>
        <div class="panel-card">
            <div class="panel-card-header">
                <span class="panel-title">Debi Göstergesi</span>
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
                        Bu ayki hane sayaç kaydınız: <strong><?= number_format((float) $quickWater['my_log']['consumption_m3'], 1, ',', '.') ?> m³</strong>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-mini">
                    <p>Bu ay için henüz su tüketim verisi girmediniz.</p>
                    <a href="water.php" class="btn btn-primary btn-sm btn-block">Sayaç / Fatura Gir</a>
                </div>
            <?php endif; ?>

            <div class="comparison-bars">
                <div class="comp-item">
                    <div class="comp-row">
                        <span>Mahalle Ortalaması</span>
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
                <?php endif; ?>

                <div class="comp-item">
                    <div class="comp-row">
                        <span>Tasarruflu Hedef Eşiği</span>
                        <strong>100,0 L / gün</strong>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill fill-target" style="width: 50%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-card-header">
                <span class="panel-title">Mahalle Tasarruf Sıralaması</span>
                <a href="neighborhood.php" class="link-subtle">Tümü</a>
            </div>
            <p class="panel-desc">
                <?= e($currentUser['neighborhood_name']) ?> içinde bu ay kişi başı en az su tüketen haneler (apartman daireleri &amp; bireysel evler):
            </p>

            <?php if (!empty($overview['leaderboard'])): ?>
                <ol class="leaderboard-list">
                    <?php foreach ($overview['leaderboard'] as $idx => $row): ?>
                        <li class="leaderboard-item">
                            <span class="rank-num"><?= $idx + 1 ?></span>
                            <div class="rank-info">
                                <div class="rank-name"><?= e($row['full_name']) ?></div>
                                <div class="rank-sub">
                                    <?php if (!empty($row['apartment_name'])): ?>
                                        <?= e($row['apartment_name']) ?> · <?= e($row['unit_label']) ?>
                                    <?php else: ?>
                                        <?= e(housing_type_label($row['housing_type'])) ?> · <?= e($row['unit_label']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="rank-val"><?= number_format((float) $row['daily_liters_per_person'], 1, ',', '.') ?> L</span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p class="muted-note">Mahallenizde bu ay henüz sayaç verisi paylaşılmadı. İlk veriyi girerek sıralamayı başlatabilirsiniz.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</aside>

