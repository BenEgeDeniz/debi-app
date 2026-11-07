<?php
/**
 * DEBI - Sol Navigasyon Sütunu (Masaüstü) & Alt Sabit Menü (Mobil)
 */
$activeNav = $activeNav ?? 'feed';
?>
<aside class="sidebar-left">
    <nav class="side-nav" aria-label="Ana Menü">
        <a href="index.php" class="side-nav-link <?= $activeNav === 'feed' ? 'active' : '' ?>">
            <span>Komşu Akışı</span>
        </a>

        <a href="water.php" class="side-nav-link <?= $activeNav === 'water' ? 'active' : '' ?>">
            <span>Fatura Yükle &amp; Takip</span>
        </a>

        <a href="neighborhood.php" class="side-nav-link <?= $activeNav === 'neighborhood' ? 'active' : '' ?>">
            <span>Mahallem</span>
        </a>

        <a href="apartment.php" class="side-nav-link <?= $activeNav === 'apartment' ? 'active' : '' ?>">
            <span>
                <?= !empty($currentUser['apartment_id']) ? 'Apartmanım' : 'Apartmana Katıl' ?>
            </span>
        </a>

        <a href="profile.php" class="side-nav-link <?= $activeNav === 'profile' ? 'active' : '' ?>">
            <span>Profil &amp; Hane</span>
        </a>
    </nav>

    <?php if (!empty($currentUser)): ?>
        <div class="panel-card household-card">
            <div class="panel-card-header">
                <span class="panel-title">Hane Özeti</span>
                <?php if ($currentUser['role'] === 'manager'): ?>
                    <span class="tag tag-manager">Yönetici</span>
                <?php endif; ?>
            </div>
            <div class="household-meta-list">
                <div class="meta-row">
                    <span class="meta-label">Mahalle</span>
                    <span class="meta-val"><?= e($currentUser['neighborhood_name']) ?></span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Konut</span>
                    <span class="meta-val">
                        <?php if (!empty($currentUser['apartment_id'])): ?>
                            <?= e($currentUser['apartment_name']) ?>
                        <?php else: ?>
                            <?= e(housing_type_label($currentUser['housing_type'])) ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Daire / Kişi</span>
                    <span class="meta-val"><?= e($currentUser['unit_label']) ?> (<?= (int) $currentUser['resident_count'] ?> kişi)</span>
                </div>
            </div>

            <?php if (!empty($currentUser['apartment_invite_code'])): ?>
                <div class="invite-mini-box">
                    <span class="invite-mini-label">Apartman Davet Kodu</span>
                    <div class="invite-code-row">
                        <code id="sidebarInviteCode"><?= e($currentUser['apartment_invite_code']) ?></code>
                        <button type="button" class="btn-copy" data-copy="<?= e($currentUser['apartment_invite_code']) ?>">Kopyala</button>
                    </div>
                </div>
            <?php else: ?>
                <div class="invite-mini-box">
                    <span class="invite-mini-label">Bireysel Katılımcı</span>
                    <a href="apartment.php" class="link-subtle">Apartman davet kodunuz var mı? &rarr;</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</aside>

<!-- Mobil Alt Sabit Menü -->
<nav class="mobile-bottom-nav" aria-label="Mobil Menü">
    <a href="index.php" class="mobile-nav-item <?= $activeNav === 'feed' ? 'active' : '' ?>">
        <span>Akış</span>
    </a>
    <a href="water.php" class="mobile-nav-item <?= $activeNav === 'water' ? 'active' : '' ?>">
        <span>Fatura</span>
    </a>
    <a href="neighborhood.php" class="mobile-nav-item <?= $activeNav === 'neighborhood' ? 'active' : '' ?>">
        <span>Mahalle</span>
    </a>
    <a href="apartment.php" class="mobile-nav-item <?= $activeNav === 'apartment' ? 'active' : '' ?>">
        <span>Apartman</span>
    </a>
    <a href="profile.php" class="mobile-nav-item <?= $activeNav === 'profile' ? 'active' : '' ?>">
        <span>Profil</span>
    </a>
</nav>
