/**
 * DEBI - İstemci Tarafı Etkileşimleri (Vanilla JS)
 */
document.addEventListener('DOMContentLoaded', function () {

    // 1. Kayıt Sayfası Mod Seçimi (Bireysel / Davetli Daire / Apartman Yöneticisi)
    const modeTabs = document.querySelectorAll('.mode-tab[data-mode-target]');
    const modeInput = document.getElementById('registerModeInput');
    const locationSection = document.getElementById('locationSection');
    const modeBoxes = document.querySelectorAll('.mode-specific[data-mode-box]');

    if (modeTabs.length > 0 && modeInput) {
        modeTabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                const targetMode = tab.getAttribute('data-mode-target');
                modeInput.value = targetMode;

                modeTabs.forEach(function (t) {
                    t.classList.toggle('active', t === tab);
                });

                if (locationSection) {
                    locationSection.style.display = (targetMode === 'apartment_invite') ? 'none' : '';
                }

                modeBoxes.forEach(function (box) {
                    const isMatch = box.getAttribute('data-mode-box') === targetMode;
                    box.style.display = isMatch ? '' : 'none';

                    // Aynı isimli unit_label alanlarının çakışmaması için sadece aktif olanı etkinleştir
                    const unitInput = box.querySelector('input[name="unit_label"]');
                    if (unitInput) {
                        unitInput.disabled = !isMatch;
                    }
                });
            });
        });
    }

    // 2. Davet Kodu Kopyalama Butonları
    const copyButtons = document.querySelectorAll('.btn-copy[data-copy]');
    copyButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const text = btn.getAttribute('data-copy');
            if (!text) return;

            const originalLabel = btn.textContent;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    btn.textContent = 'Kopyalandı';
                    setTimeout(function () {
                        btn.textContent = originalLabel;
                    }, 1600);
                });
            } else {
                const temp = document.createElement('input');
                temp.value = text;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
                btn.textContent = 'Kopyalandı';
                setTimeout(function () {
                    btn.textContent = originalLabel;
                }, 1600);
            }
        });
    });

    // 3. Yorum Kutusuna Odaklanma
    const commentToggles = document.querySelectorAll('.js-toggle-comments');
    commentToggles.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetId = btn.getAttribute('data-target');
            const box = document.getElementById(targetId);
            if (!box) return;
            const input = box.querySelector('input[name="comment_content"]');
            if (input) {
                input.focus();
            }
        });
    });

    // 5. Otomatik İl > İlçe > Mahalle Seçimi (API Destekli)
    const locationSection = document.getElementById('locationSection');
    const citySelect = document.getElementById('citySelect');
    const districtSelect = document.getElementById('districtSelect');
    const neighborhoodSelect = document.getElementById('neighborhoodSelect');
    const toggleManualBtn = document.getElementById('toggleManualLocation');
    const autoLocationBox = document.getElementById('autoLocationInputs');
    const manualLocationBox = document.getElementById('manualLocationInputs');

    if (citySelect && districtSelect && neighborhoodSelect) {
        let isManual = false;
        const prefillCity = locationSection ? locationSection.getAttribute('data-prefill-city') : '';
        const prefillDistrict = locationSection ? locationSection.getAttribute('data-prefill-district') : '';
        const prefillNeighborhood = locationSection ? locationSection.getAttribute('data-prefill-neighborhood') : '';

        // API İstek Yardımcısı (Önce yerel proxy, başarısızsa doğrudan TurkiyeAPI genel uç noktası)
        async function fetchLocations(action, paramKey, paramVal) {
            const localUrl = `api/locations.php?action=${action}${paramKey ? `&${paramKey}=${paramVal}` : ''}`;
            try {
                const res = await fetch(localUrl);
                const json = await res.json();
                if (json && json.ok && Array.isArray(json.data) && json.data.length > 0) {
                    return json.data;
                }
            } catch (err) {
                // Yerel istek başarısız olduysa genel API'ye düş
            }

            // Fallback: Doğrudan api.turkiyeapi.dev
            try {
                let directUrl = '';
                if (action === 'provinces') {
                    directUrl = 'https://api.turkiyeapi.dev/v2/provinces?fields=id,name&sort=name';
                } else if (action === 'districts') {
                    directUrl = `https://api.turkiyeapi.dev/v2/provinces/${paramVal}/districts?fields=id,name&sort=name`;
                } else if (action === 'neighborhoods') {
                    directUrl = `https://api.turkiyeapi.dev/v2/districts/${paramVal}/neighborhoods?fields=id,name&sort=name`;
                }
                if (directUrl) {
                    const fallbackRes = await fetch(directUrl);
                    const fallbackJson = await fallbackRes.json();
                    if (fallbackJson && fallbackJson.data) {
                        return fallbackJson.data;
                    }
                }
            } catch (fallbackErr) {
                console.error('Konum API hatası:', fallbackErr);
            }
            return [];
        }

        // İlleri Doldur
        async function loadProvinces() {
            citySelect.innerHTML = '<option value="">İl Seçiniz...</option>';
            const provinces = await fetchLocations('provinces');

            if (!provinces || provinces.length === 0) {
                citySelect.innerHTML = '<option value="">İller yüklenemedi (Elle girişe geçin)</option>';
                return;
            }

            provinces.forEach(function (p) {
                const opt = document.createElement('option');
                opt.value = p.name;
                opt.setAttribute('data-id', p.id);
                opt.textContent = p.name;
                if (prefillCity && (prefillCity.toLowerCase() === p.name.toLowerCase())) {
                    opt.selected = true;
                }
                citySelect.appendChild(opt);
            });

            if (citySelect.value) {
                citySelect.dispatchEvent(new Event('change'));
            }
        }

        // İlçe Yükle
        citySelect.addEventListener('change', async function () {
            const selectedOpt = citySelect.options[citySelect.selectedIndex];
            const provinceId = selectedOpt ? selectedOpt.getAttribute('data-id') : null;

            districtSelect.innerHTML = '<option value="">Önce il seçin</option>';
            districtSelect.disabled = true;
            neighborhoodSelect.innerHTML = '<option value="">Önce ilçe seçin</option>';
            neighborhoodSelect.disabled = true;

            if (!provinceId) return;

            districtSelect.innerHTML = '<option value="">İlçeler yükleniyor...</option>';
            const districts = await fetchLocations('districts', 'province_id', provinceId);

            districtSelect.innerHTML = '<option value="">İlçe Seçiniz...</option>';
            districts.forEach(function (d) {
                const opt = document.createElement('option');
                opt.value = d.name;
                opt.setAttribute('data-id', d.id);
                opt.textContent = d.name;
                if (prefillDistrict && (prefillDistrict.toLowerCase() === d.name.toLowerCase())) {
                    opt.selected = true;
                }
                districtSelect.appendChild(opt);
            });
            districtSelect.disabled = false;

            if (districtSelect.value) {
                districtSelect.dispatchEvent(new Event('change'));
            }
        });

        // Mahalle Yükle
        districtSelect.addEventListener('change', async function () {
            const selectedOpt = districtSelect.options[districtSelect.selectedIndex];
            const districtId = selectedOpt ? selectedOpt.getAttribute('data-id') : null;

            neighborhoodSelect.innerHTML = '<option value="">Önce ilçe seçin</option>';
            neighborhoodSelect.disabled = true;

            if (!districtId) return;

            neighborhoodSelect.innerHTML = '<option value="">Mahalleler yükleniyor...</option>';
            const neighborhoods = await fetchLocations('neighborhoods', 'district_id', districtId);

            neighborhoodSelect.innerHTML = '<option value="">Mahalle Seçiniz...</option>';
            neighborhoods.forEach(function (n) {
                let mName = n.name;
                if (!mName.toLowerCase().endsWith('mah.') && !mName.toLowerCase().endsWith('mahallesi')) {
                    mName += ' Mah.';
                }
                const opt = document.createElement('option');
                opt.value = mName;
                opt.setAttribute('data-id', n.id);
                opt.textContent = mName;
                if (prefillNeighborhood && (prefillNeighborhood.toLowerCase() === mName.toLowerCase())) {
                    opt.selected = true;
                }
                neighborhoodSelect.appendChild(opt);
            });
            neighborhoodSelect.disabled = false;
        });

        // Manuel / Otomatik Giriş Arasında Geçiş Yap
        if (toggleManualBtn && autoLocationBox && manualLocationBox) {
            const manCity = document.getElementById('manualCity');
            const manDist = document.getElementById('manualDistrict');
            const manNeigh = document.getElementById('manualNeighborhood');

            toggleManualBtn.addEventListener('click', function () {
                isManual = !isManual;
                if (isManual) {
                    autoLocationBox.style.display = 'none';
                    manualLocationBox.style.display = '';

                    // İsimleri manuel alanlara ver
                    citySelect.removeAttribute('name');
                    districtSelect.removeAttribute('name');
                    neighborhoodSelect.removeAttribute('name');

                    manCity.setAttribute('name', 'city');
                    manDist.setAttribute('name', 'district');
                    manNeigh.setAttribute('name', 'neighborhood_name');

                    manCity.disabled = false;
                    manDist.disabled = false;
                    manNeigh.disabled = false;

                    manCity.value = citySelect.value || '';
                    manDist.value = districtSelect.value || '';
                    manNeigh.value = neighborhoodSelect.value || '';

                    toggleManualBtn.textContent = 'Listeden otomatik seçime dön ←';
                } else {
                    autoLocationBox.style.display = '';
                    manualLocationBox.style.display = 'none';

                    // İsimleri select kutularına geri ver
                    manCity.removeAttribute('name');
                    manDist.removeAttribute('name');
                    manNeigh.removeAttribute('name');
                    manCity.disabled = true;
                    manDist.disabled = true;
                    manNeigh.disabled = true;

                    citySelect.setAttribute('name', 'city');
                    districtSelect.setAttribute('name', 'district');
                    neighborhoodSelect.setAttribute('name', 'neighborhood_name');

                    toggleManualBtn.textContent = 'Listede bulamadım, elle yaz →';
                }
            });
        }

        // Sayfa açıldığında illeri yükle
        loadProvinces();
    }
});

