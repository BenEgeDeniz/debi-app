<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/community/community_service.php';

$currentUser = require_login();
$pageTitle = 'Puan Sıralaması — Debi';
$activeNav = 'leaderboard';
$friendLeaders = get_friends_drops_leaderboard($currentUser, 20);
$aptLeaders = !empty($currentUser['apartment_id'])
    ? get_apartment_drops_leaderboard($currentUser, 20)
    : [];
$genLeaders = get_general_apartments_drops_leaderboard(20);

function render_leaderboard_rows(array $rows, string $type, array $currentUser): void
{
    if (!$rows) {
        $empty = [
            'friends' => 'Henüz ekli arkadaşınız yok.',
            'apartment' => 'Apartmanda henüz puanlı daire yok.',
            'general' => 'Henüz apartman kaydı yok.',
        ][$type];
        echo '<p class="muted-note leaderboard-empty">' . e($empty) . '</p>';
        return;
    }
    ?>
    <ol class="leaderboard-list leaderboard-page-list leaderboard-scroll">
        <?php foreach ($rows as $idx => $row): ?>
            <?php
            $isMe = $type === 'friends' && (int) $row['user_id'] === (int) $currentUser['id'];
            $isMyUnit = $type === 'apartment' && (int) $row['household_id'] === (int) $currentUser['household_id'];
            $isMyApt = $type === 'general' && !empty($currentUser['apartment_id'])
                && (int) $row['apartment_id'] === (int) $currentUser['apartment_id'];
            ?>
            <li class="leaderboard-item <?= ($isMe || $isMyUnit || $isMyApt) ? 'leaderboard-me' : '' ?>">
                <span class="rank-num"><?= $idx + 1 ?></span>
                <div class="rank-info">
                    <div class="rank-name">
                        <?php if ($type === 'friends'): ?>
                            <a href="profile.php?id=<?= (int) $row['user_id'] ?>" class="author-link">
                                <?= e($row['full_name']) ?><?= $isMe ? ' <small>(Sen)</small>' : '' ?>
                            </a>
                        <?php elseif ($type === 'apartment'): ?>
                            <strong><?= e(format_unit_label($row['unit_label'], $row['housing_type'])) ?></strong><?= $isMyUnit ? ' <small>(Daireniz)</small>' : '' ?>
                        <?php else: ?>
                            <strong><?= e($row['apartment_name']) ?></strong><?= $isMyApt ? ' <small>(Apartmanınız)</small>' : '' ?>
                        <?php endif; ?>
                    </div>
                    <div class="rank-sub">
                        <?php if ($type === 'friends'): ?>
                            <?= !empty($row['apartment_name']) ? e($row['apartment_name'] . ' · ' . $row['unit_label']) : e($row['unit_label']) ?>
                        <?php elseif ($type === 'apartment'): ?>
                            <?= e($row['display_name']) ?>
                        <?php else: ?>
                            <?= e($row['district'] . ' / ' . $row['city']) ?> · <?= (int) $row['daire_count'] ?> Daire
                        <?php endif; ?>
                    </div>
                </div>
                <span class="rank-val">💧 <?= number_format((int) $row['total_drops'], 0, ',', '.') ?> Puan</span>
            </li>
        <?php endforeach; ?>
    </ol>
    <?php
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="app-shell app-shell-2col">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>
    <main class="main-column leaderboard-page">
        <div class="page-heading-row">
            <div>
                <h1 class="page-title">Puan Sıralaması</h1>
                <p class="page-subtitle">Hanelerin biriktirdiği damlaları arkadaş, apartman ve genel ölçekte karşılaştır.</p>
            </div>
        </div>
        <section class="leaderboard-page-grid">
            <article class="panel-card leaderboard-board">
                <div class="leaderboard-board-header">
                    <div><span class="panel-title">Arkadaşlar</span><p>Arkadaşlarının hane puanları</p></div>
                    <span class="leaderboard-board-icon">👥</span>
                </div>
                <?php render_leaderboard_rows($friendLeaders, 'friends', $currentUser); ?>
            </article>

            <?php if (!empty($currentUser['apartment_id'])): ?>
                <article class="panel-card leaderboard-board">
                    <div class="leaderboard-board-header">
                        <div><span class="panel-title">Apartman</span><p>Apartmandaki dairelerin puanları</p></div>
                        <span class="leaderboard-board-icon">🏢</span>
                    </div>
                    <?php render_leaderboard_rows($aptLeaders, 'apartment', $currentUser); ?>
                </article>
            <?php endif; ?>

            <article class="panel-card leaderboard-board">
                <div class="leaderboard-board-header">
                    <div><span class="panel-title">Genel</span><p>Apartmanların toplam puanları</p></div>
                    <span class="leaderboard-board-icon">🌍</span>
                </div>
                <?php render_leaderboard_rows($genLeaders, 'general', $currentUser); ?>
            </article>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
