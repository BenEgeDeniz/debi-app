<?php
/**
 * DEBI - Sol Navigasyon Sütunu (Masaüstü) & Alt Sabit Menü (Mobil)
 */
$activeNav = $activeNav ?? 'feed';
?>
<aside class="sidebar-left">
    <nav class="side-nav" aria-label="Ana Menü">
        <a href="index.php" class="side-nav-link <?= $activeNav === 'feed' ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75">
                <path d="M3 9.5L10 3l7 6.5V17a1 1 0 0 1-1 1h-4v-5H8v5H4a1 1 0 0 1-1-1V9.5z"/>
            </svg>
            <span>Sosyal Akış</span>
        </a>

        <a href="neighborhood.php" class="side-nav-link <?= $activeNav === 'neighborhood' ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75">
                <path d="M2.5 17.5h15M4 17.5V7l6-4 6 4v10.5M8 10.5h4M8 14h4"/>
            </svg>
            <span>Mahallem</span>
        </a>

        <a href="apartment.php" class="side-nav-link <?= $activeNav === 'apartment' ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75">
                <rect x="4" y="2.5" width="12" height="15" rx="1"/>
                <path d="M7.5 6h1.5M11 6h1.5M7.5 9.5h1.5M11 9.5h1.5M7.5 13h1.5M11 13h1.5M9 17.5v-2.5h2v2.5"/>
            </svg>
            <span>
                <?= !empty($currentUser['apartment_id']) ? 'Apartmanım' : 'Apartman Bağlantısı' ?>
            </span>
        </a>

        <a href="water.php" class="side-nav-link <?= $activeNav === 'water' ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75">
                <path d="M10 2.5C10 2.5 4.5 9 4.5 13a5.5 5.5 0 0 0 11 0c0-4-5.5-10.5-5.5-10.5z"/>
            </svg>
            <span>Su Tüketimi &amp; Sayaç</span>
        </a>

        <a href="profile.php" class="side-nav-link <?= $activeNav === 'profile' ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75">
                <circle cx="10" cy="6.5" r="3.25"/>
                <path d="M3.5 17c0-3.2 2.9-5.5 6.5-5.5s6.5 2.3 6.5 5.5"/>
            </svg>
            <span>Hane &amp; Profil</span>
        </a>
    </nav>

    <?php if (!empty($currentUser)): ?>
        <div class="panel-card household-card">
            <div class="panel-card-header">
                <span class="panel-title">Hane Kimliği</span>
                <?php if ($currentUser['role'] === 'manager'): ?>
                    <span class="tag tag-manager">Apartman Yöneticisi</span>
                <?php endif; ?>
            </div>
            <div class="household-meta-list">
                <div class="meta-row">
                    <span class="meta-label">Mahalle:</span>
                    <span class="meta-val"><?= e($currentUser['neighborhood_name']) ?></span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">İlçe / İl:</span>
                    <span class="meta-val"><?= e($currentUser['neighborhood_district'] . ' / ' . $currentUser['neighborhood_city']) ?></span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Konut:</span>
                    <span class="meta-val">
                        <?php if (!empty($currentUser['apartment_id'])): ?>
                            <?= e($currentUser['apartment_name']) ?>
                        <?php else: ?>
                            <?= e(housing_type_label($currentUser['housing_type'])) ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Birim / Ev:</span>
                    <span class="meta-val"><?= e($currentUser['unit_label']) ?> (<?= (int) $currentUser['resident_count'] ?> kişi)</span>
                </div>
            </div>

            <?php if (!empty($currentUser['apartment_invite_code'])): ?>
                <div class="invite-mini-box">
                    <span class="invite-mini-label">Apartman Davet Kodu:</span>
                    <div class="invite-code-row">
                        <code id="sidebarInviteCode"><?= e($currentUser['apartment_invite_code']) ?></code>
                        <button type="button" class="btn-copy" data-copy="<?= e($currentUser['apartment_invite_code']) ?>">Kopyala</button>
                    </div>
                </div>
            <?php else: ?>
                <div class="invite-mini-box">
                    <span class="invite-mini-label">Mahallede Bireysel Üyesiniz</span>
                    <a href="apartment.php" class="link-subtle">Apartman davet kodunuz var mı? &rarr;</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</aside>

<!-- Mobil Alt Sabit Menü -->
<nav class="mobile-bottom-nav" aria-label="Mobil Menü">
    <a href="index.php" class="mobile-nav-item <?= $activeNav === 'feed' ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75">
            <path d="M3 9.5L10 3l7 6.5V17a1 1 0 0 1-1 1h-4v-5H8v5H4a1 1 0 0 1-1-1V9.5z"/>
        </svg>
        <span>Akış</span>
    </a>
    <a href="neighborhood.php" class="mobile-nav-item <?= $activeNav === 'neighborhood' ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75">
            <path d="M2.5 17.5h15M4 17.5V7l6-4 6 4v10.5M8 10.5h4M8 14h4"/>
        </svg>
        <span>Mahalle</span>
    </a>
    <a href="water.php" class="mobile-nav-item <?= $activeNav === 'water' ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75">
            <path d="M10 2.5C10 2.5 4.5 9 4.5 13a5.5 5.5 0 0 0 11 0c0-4-5.5-10.5-5.5-10.5z"/>
        </svg>
        <span>Su Gir</span>
    </a>
    <a href="apartment.php" class="mobile-nav-item <?= $activeNav === 'apartment' ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75">
            <rect x="4" y="2.5" width="12" height="15" rx="1"/>
            <path d="M7.5 6h1.5M11 6h1.5M7.5 9.5h1.5M11 9.5h1.5M7.5 13h1.5M11 13h1.5"/>
        </svg>
        <span>Apartman</span>
    </a>
    <a href="profile.php" class="mobile-nav-item <?= $activeNav === 'profile' ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75">
            <circle cx="10" cy="6.5" r="3.25"/>
            <path d="M3.5 17c0-3.2 2.9-5.5 6.5-5.5s6.5 2.3 6.5 5.5"/>
        </svg>
        <span>Profil</span>
    </a>
</nav>

