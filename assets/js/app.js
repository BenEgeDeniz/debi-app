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

    // 4. AJAX Damla Desteği (Beğeni)
    const reactionForms = document.querySelectorAll('.reaction-form');
    reactionForms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(form);
            const btn = form.querySelector('.btn-action');
            const countSpan = form.querySelector('.reaction-count');

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
                .then(function (res) {
                    return res.json();
                })
                .then(function (data) {
                    if (data && data.ok) {
                        if (btn) {
                            btn.classList.toggle('reacted', !!data.reacted);
                        }
                        if (countSpan) {
                            countSpan.textContent = '(' + data.count + ')';
                        }
                    } else {
                        form.submit();
                    }
                })
                .catch(function () {
                    form.submit();
                });
        });
    });

    // 5. Otomatik İl > İlçe > Mahalle Seçimi (Yalnızca Listeden Seçim)
    const citySelect = document.getElementById('citySelect');
    const districtSelect = document.getElementById('districtSelect');
    const neighborhoodSelect = document.getElementById('neighborhoodSelect');

    if (citySelect && districtSelect && neighborhoodSelect) {
        const prefillDistrict = locationSection ? (locationSection.getAttribute('data-prefill-district') || '') : '';
        const prefillNeighborhood = locationSection ? (locationSection.getAttribute('data-prefill-neighborhood') || '') : '';

        async function fetchLocationList(action, paramKey, paramVal) {
            // 1. Önce yerel PHP proxy + cache uç noktasını dene
            const localUrl = 'api/locations.php?action=' + encodeURIComponent(action) +
                (paramKey ? '&' + encodeURIComponent(paramKey) + '=' + encodeURIComponent(paramVal) : '');
            try {
                const res = await fetch(localUrl);
                if (res.ok) {
                    const json = await res.json();
                    if (json && json.ok && Array.isArray(json.data) && json.data.length > 0) {
                        return json.data;
                    }
                }
            } catch (e) {
                // Yerel uç nokta hata verirse doğrudan API'ye geç
            }

            // 2. Doğrudan TurkiyeAPI v2 dene (Test edilmiş gerçek uç noktalar)
            try {
                let v2Url = '';
                if (action === 'districts') {
                    v2Url = 'https://api.turkiyeapi.dev/v2/provinces/' + encodeURIComponent(paramVal) + '/districts';
                } else if (action === 'neighborhoods') {
                    v2Url = 'https://api.turkiyeapi.dev/v2/neighborhoods?districtId=' + encodeURIComponent(paramVal) + '&limit=100';
                }
                if (v2Url) {
                    const resV2 = await fetch(v2Url);
                    if (resV2.ok) {
                        const jsonV2 = await resV2.json();
                        if (jsonV2 && Array.isArray(jsonV2.data) && jsonV2.data.length > 0) {
                            const sorted = jsonV2.data.slice().sort(function (a, b) {
                                return (a.name || '').localeCompare(b.name || '', 'tr');
                            });
                            return sorted;
                        }
                    }
                }
            } catch (e2) {
                console.error('Konum verisi çekilemedi:', e2);
            }

            return [];
        }

        // İl seçildiğinde ilçeleri yükle
        citySelect.addEventListener('change', async function () {
            const selectedOpt = citySelect.options[citySelect.selectedIndex];
            const provinceId = selectedOpt ? selectedOpt.getAttribute('data-id') : null;

            districtSelect.innerHTML = '<option value="">Önce il seçin</option>';
            districtSelect.disabled = true;
            neighborhoodSelect.innerHTML = '<option value="">Önce ilçe seçin</option>';
            neighborhoodSelect.disabled = true;

            if (!provinceId) {
                return;
            }

            districtSelect.innerHTML = '<option value="">İlçeler yükleniyor...</option>';
            const districts = await fetchLocationList('districts', 'province_id', provinceId);

            if (!districts || districts.length === 0) {
                districtSelect.innerHTML = '<option value="">İlçeler yüklenemedi, tekrar deneyin</option>';
                return;
            }

            districtSelect.innerHTML = '<option value="">İlçe Seçiniz...</option>';
            districts.forEach(function (d) {
                const opt = document.createElement('option');
                opt.value = d.name;
                opt.setAttribute('data-id', d.id);
                opt.textContent = d.name;
                if (prefillDistrict && prefillDistrict.toLocaleLowerCase('tr-TR') === d.name.toLocaleLowerCase('tr-TR')) {
                    opt.selected = true;
                }
                districtSelect.appendChild(opt);
            });
            districtSelect.disabled = false;

            if (districtSelect.value) {
                districtSelect.dispatchEvent(new Event('change'));
            }
        });

        // İlçe seçildiğinde mahalleleri yükle
        districtSelect.addEventListener('change', async function () {
            const selectedOpt = districtSelect.options[districtSelect.selectedIndex];
            const districtId = selectedOpt ? selectedOpt.getAttribute('data-id') : null;

            neighborhoodSelect.innerHTML = '<option value="">Önce ilçe seçin</option>';
            neighborhoodSelect.disabled = true;

            if (!districtId) {
                return;
            }

            neighborhoodSelect.innerHTML = '<option value="">Mahalleler yükleniyor...</option>';
            const neighborhoods = await fetchLocationList('neighborhoods', 'district_id', districtId);

            if (!neighborhoods || neighborhoods.length === 0) {
                neighborhoodSelect.innerHTML = '<option value="">Mahalleler yüklenemedi, tekrar deneyin</option>';
                return;
            }

            neighborhoodSelect.innerHTML = '<option value="">Mahalle Seçiniz...</option>';
            neighborhoods.forEach(function (n) {
                let mName = (n.name || '').trim();
                const lower = mName.toLocaleLowerCase('tr-TR');
                if (!lower.endsWith('mah.') && !lower.endsWith('mahallesi')) {
                    mName += ' Mah.';
                }
                const opt = document.createElement('option');
                opt.value = mName;
                opt.setAttribute('data-id', n.id);
                opt.textContent = mName;
                if (prefillNeighborhood && prefillNeighborhood.toLocaleLowerCase('tr-TR') === mName.toLocaleLowerCase('tr-TR')) {
                    opt.selected = true;
                }
                neighborhoodSelect.appendChild(opt);
            });
            neighborhoodSelect.disabled = false;
        });

        // Sayfa yüklendiğinde seçili bir il varsa ilçelerini otomatik tetikle
        if (citySelect.value) {
            citySelect.dispatchEvent(new Event('change'));
        }
    }

    // 6. Su Faturası Fotoğraf Önizleme & Yapay Zeka Tarama Durumu
    const billInput = document.getElementById('bill_image_input');
    const billDropzoneText = document.getElementById('billDropzoneText');
    const billSelectedPreview = document.getElementById('billSelectedPreview');
    const billPreviewImg = document.getElementById('billPreviewImg');
    const billPreviewName = document.getElementById('billPreviewName');
    const billScanForm = document.getElementById('billScanForm');
    const billScanSubmitBtn = document.getElementById('billScanSubmitBtn');

    if (billInput) {
        billInput.addEventListener('change', function () {
            const file = billInput.files && billInput.files[0];
            if (!file) return;

            if (billPreviewName) {
                billPreviewName.textContent = file.name + ' (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB)';
            }

            if (billPreviewImg && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function (ev) {
                    billPreviewImg.src = ev.target.result;
                    if (billDropzoneText) billDropzoneText.hidden = true;
                    if (billSelectedPreview) billSelectedPreview.hidden = false;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    if (billScanForm && billScanSubmitBtn) {
        billScanForm.addEventListener('submit', function () {
            billScanSubmitBtn.disabled = true;
            billScanSubmitBtn.textContent = 'Yapay zeka faturayı okuyor, lütfen bekleyin...';
        });
    }

    // 7. Fatura Onay Ekranında Canlı Kişi Başı Günlük Litre Hesabı
    const billConfirmForm = document.getElementById('billConfirmForm');
    const m3Input = document.getElementById('consumption_m3');
    const daysInput = document.getElementById('reading_days');
    const liveDailyEl = document.getElementById('liveDailyLiters');

    if (billConfirmForm && m3Input && daysInput && liveDailyEl) {
        const residents = Math.max(1, parseInt(billConfirmForm.getAttribute('data-residents') || '2', 10));
        function updateLiveDaily() {
            const m3 = parseFloat((m3Input.value || '0').replace(',', '.'));
            const days = Math.max(1, parseInt(daysInput.value || '30', 10));
            if (m3 > 0) {
                const daily = ((m3 * 1000) / (days * residents)).toFixed(1).replace('.', ',');
                liveDailyEl.textContent = daily + ' L / gün';
            } else {
                liveDailyEl.textContent = '—';
            }
        }
        m3Input.addEventListener('input', updateLiveDaily);
        daysInput.addEventListener('input', updateLiveDaily);
    }

    // 8. Zaman Tünelinde "Profili & Haneyi Düzenle" Kutusunu Aç/Kapat
    const toggleEditProfileBtn = document.getElementById('toggleEditProfileBtn');
    const editProfileSection = document.getElementById('editProfileSection');
    if (toggleEditProfileBtn && editProfileSection) {
        toggleEditProfileBtn.addEventListener('click', function () {
            editProfileSection.hidden = !editProfileSection.hidden;
            if (!editProfileSection.hidden) {
                editProfileSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    }
});
