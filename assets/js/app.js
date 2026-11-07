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

    // 7. Fatura Onay Ekranında Canlı Kişi Başı Günlük Litre & 1. Puanlama Yöntemi Hesabı
    const billConfirmForm = document.getElementById('billConfirmForm');
    const prevM3Input = document.getElementById('previous_consumption_m3');
    const m3Input = document.getElementById('consumption_m3');
    const daysInput = document.getElementById('reading_days');
    const liveDailyEl = document.getElementById('liveDailyLiters');

    if (billConfirmForm && m3Input && daysInput && liveDailyEl) {
        const residents = Math.max(1, parseInt(billConfirmForm.getAttribute('data-residents') || '2', 10));
        function updateLiveDaily() {
            const prevM3 = prevM3Input ? parseFloat((prevM3Input.value || '0').replace(',', '.')) : 0;
            const m3 = parseFloat((m3Input.value || '0').replace(',', '.'));
            const days = Math.max(1, parseInt(daysInput.value || '30', 10));
            if (m3 > 0) {
                const daily = ((m3 * 1000) / (days * residents)).toFixed(1).replace('.', ',');
                let suffix = '';
                if (prevM3 > 0) {
                    const diffM3 = prevM3 - m3;
                    if (diffM3 > 0) {
                        const savedL = Math.round(diffM3 * 1000).toLocaleString('tr-TR');
                        const pct = ((diffM3 / prevM3) * 100);
                        const pts = pct >= 10 ? ' · +10 Debi Puanı 💧' : ' · Baraj Altı (0 Puan)';
                        suffix = ' (' + savedL + ' L Tasarruf · %' + pct.toFixed(1).replace('.', ',') + pts + ')';
                    } else {
                        suffix = ' (0 L Tasarruf · 0 Puan)';
                    }
                }
                liveDailyEl.textContent = daily + ' L / gün' + suffix;
            } else {
                liveDailyEl.textContent = '—';
            }
        }
        if (prevM3Input) prevM3Input.addEventListener('input', updateLiveDaily);
        m3Input.addEventListener('input', updateLiveDaily);
        daysInput.addEventListener('input', updateLiveDaily);
        updateLiveDaily();
    }

    // 8. Zaman Tünelinde "Profili Düzenle" Kutusunu Aç/Kapat ve Canlı Görsel Önizleme
    const openEditBtns = document.querySelectorAll('.js-open-profile-edit');
    const editProfileSection = document.getElementById('editProfileSection');
    if (openEditBtns.length > 0 && editProfileSection) {
        openEditBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                editProfileSection.hidden = !editProfileSection.hidden;
                if (!editProfileSection.hidden) {
                    editProfileSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        });
    }

    const avatarPhotoInput = document.getElementById('avatar_photo_input');
    const avatarPreviewEl = document.getElementById('avatarPreviewEl');
    const avatarInitialsEl = document.getElementById('avatarInitialsEl');
    const avatarFileName = document.getElementById('avatarFileName');
    if (avatarPhotoInput) {
        avatarPhotoInput.addEventListener('change', function () {
            const file = avatarPhotoInput.files && avatarPhotoInput.files[0];
            if (!file) return;
            if (avatarFileName) {
                avatarFileName.textContent = file.name;
            }
            if (avatarPreviewEl && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function (ev) {
                    avatarPreviewEl.src = ev.target.result;
                    avatarPreviewEl.hidden = false;
                    if (avatarInitialsEl) avatarInitialsEl.hidden = true;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    const coverPhotoInput = document.getElementById('cover_photo_input');
    const coverPickerThumb = document.getElementById('coverPickerThumb');
    const coverFileName = document.getElementById('coverFileName');
    if (coverPhotoInput) {
        coverPhotoInput.addEventListener('change', function () {
            const file = coverPhotoInput.files && coverPhotoInput.files[0];
            if (!file) return;
            if (coverFileName) {
                coverFileName.textContent = file.name;
            }
            if (coverPickerThumb && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function (ev) {
                    coverPickerThumb.style.backgroundImage = "url('" + ev.target.result + "')";
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // 9. Su Analizi Çizgi Tablosu (xlab / ylab) ve İnteraktif Debi Göleti (debi-pond) Widget'ı
    const analyticsCfg = window.DEBI_ANALYTICS_DATA;
    const svgChart = document.getElementById('waterLineChart');
    const pondImg = document.getElementById('pondStageImg');

    if (analyticsCfg && svgChart && pondImg) {
        const baseMonthly = JSON.parse(JSON.stringify(analyticsCfg.series || []));
        const baseYearly = JSON.parse(JSON.stringify(analyticsCfg.yearlySeries || analyticsCfg.series || []));
        let currentMode = 'monthly';
        let liveSeries = JSON.parse(JSON.stringify(baseMonthly));
        let currentStageKey = '';
        let autoPlayTimer = null;

        const kpiPrevLabel = document.getElementById('kpiPrevLabel');
        const kpiCurrLabel = document.getElementById('kpiCurrLabel');
        const kpiDiffLabel = document.getElementById('kpiDiffLabel');
        const kpiPrevM3 = document.getElementById('kpiPrevM3');
        const kpiCurrM3 = document.getElementById('kpiCurrM3');
        const kpiDiffM3 = document.getElementById('kpiDiffM3');
        const kpiEarnedPoints = document.getElementById('kpiEarnedPoints');
        const userWalletPointsText = document.getElementById('userWalletPointsText');
        const modeBtns = document.querySelectorAll('.js-chart-mode');

        const pondDropLayer = document.getElementById('pondDropLayer');
        const pondRippleRing = document.getElementById('pondRippleRing');
        const pondCurrentM3Text = document.getElementById('pondCurrentM3Text');
        const pondCurrentLitersText = document.getElementById('pondCurrentLitersText');
        const pondTargetM3Text = document.getElementById('pondTargetM3Text');
        const pondFillPctText = document.getElementById('pondFillPctText');
        const pondMeterFill = document.getElementById('pondMeterFill');
        const pondTotalPointsVal = document.getElementById('pondTotalPointsVal');

        const btnAutoPlay = document.getElementById('btnAutoPlayStages');
        const btnResetReal = document.getElementById('btnResetRealData');

        const basePond = analyticsCfg.collectivePond || {
            golet_mevcut_su_m3: 0,
            golet_mevcut_litre: 0,
            golet_toplam_puan: 0,
            golet_hedef_m3: 500,
            doluluk_yuzdesi: 0
        };
        const baseSavedM3 = Number(analyticsCfg.savedM3 || 0);
        const baseEarnedPts = Number(analyticsCfg.earnedPoints || 0);
        const baseWalletPts = Number(analyticsCfg.walletPoints || 0);

        function formatTR(num, decimals) {
            return Number(num).toLocaleString('tr-TR', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        }

        function spawnFallingDrops(count) {
            if (!pondDropLayer) return;
            const total = Math.min(8, Math.max(1, count));
            for (let i = 0; i < total; i++) {
                setTimeout(function () {
                    const drop = document.createElement('span');
                    drop.className = 'falling-water-drop';
                    drop.textContent = '💧';
                    const offset = (Math.random() * 120) - 60;
                    drop.style.left = 'calc(50% + ' + Math.round(offset) + 'px)';
                    pondDropLayer.appendChild(drop);

                    setTimeout(function () {
                        if (pondRippleRing) {
                            pondRippleRing.classList.remove('ripple-active');
                            void pondRippleRing.offsetWidth;
                            pondRippleRing.classList.add('ripple-active');
                        }
                    }, 520);

                    setTimeout(function () {
                        if (drop.parentNode) {
                            drop.parentNode.removeChild(drop);
                        }
                    }, 750);
                }, i * 130);
            }
        }

        function renderLineChart(dataPoints) {
            const W = 760;
            const H = 300;
            const padL = 92;
            const padR = 36;
            const padT = 34;
            const padB = 58;
            const plotW = W - padL - padR;
            const plotH = H - padT - padB;

            let maxM3 = 10;
            let minM3 = 0;
            dataPoints.forEach(function (pt) {
                if (pt.consumption_m3 > maxM3) maxM3 = pt.consumption_m3;
            });
            maxM3 = Math.ceil(maxM3 * 1.2);
            if (maxM3 % 2 !== 0) maxM3 += 1;

            const n = dataPoints.length;
            const coords = dataPoints.map(function (pt, idx) {
                const x = n > 1 ? padL + (idx / (n - 1)) * plotW : padL + plotW / 2;
                const y = padT + plotH - ((pt.consumption_m3 - minM3) / Math.max(1, maxM3 - minM3)) * plotH;
                return { x: x, y: y, pt: pt };
            });

            let svgHtml = '';

            // Y-Axis (ylab) Gridlines & Labels
            const gridSteps = 4;
            for (let g = 0; g <= gridSteps; g++) {
                const val = (maxM3 / gridSteps) * g;
                const gy = padT + plotH - (g / gridSteps) * plotH;
                svgHtml += '<line x1="' + padL + '" y1="' + gy.toFixed(1) + '" x2="' + (W - padR) + '" y2="' + gy.toFixed(1) + '" stroke="#e2e8f0" stroke-dasharray="4 4" stroke-width="1"/>';
                svgHtml += '<text x="' + (padL - 10) + '" y="' + (gy + 4).toFixed(1) + '" text-anchor="end" fill="#64748b" font-size="12" font-weight="700">' + formatTR(val, 1) + ' m³</text>';
            }

            // Axis Titles (ylab & xlab)
            const modeLabel = currentMode === 'yearly' ? 'Yıllık' : 'Aylık';
            svgHtml += '<text x="12" y="' + (padT + plotH / 2) + '" transform="rotate(-90 12 ' + (padT + plotH / 2) + ')" text-anchor="middle" fill="#334155" font-size="12" font-weight="800">Kullanılan Su (m³)</text>';
            svgHtml += '<text x="' + (padL + plotW / 2) + '" y="' + (H - 8) + '" text-anchor="middle" fill="#334155" font-size="12" font-weight="800">Zaman (' + modeLabel + ')</text>';

            // Area under line
            if (coords.length > 1) {
                const areaPts = coords.map(function (c) { return c.x.toFixed(1) + ',' + c.y.toFixed(1); }).join(' ');
                const firstX = coords[0].x.toFixed(1);
                const lastX = coords[coords.length - 1].x.toFixed(1);
                const baseY = (padT + plotH).toFixed(1);
                svgHtml += '<polygon points="' + firstX + ',' + baseY + ' ' + areaPts + ' ' + lastX + ',' + baseY + '" fill="rgba(14, 165, 233, 0.12)" />';
            }

            // Line segments
            for (let i = 0; i < coords.length - 1; i++) {
                const c1 = coords[i];
                const c2 = coords[i + 1];
                const isLastSeg = (i === coords.length - 2);
                const isSaving = c2.pt.consumption_m3 < c1.pt.consumption_m3;
                const strokeColor = isLastSeg ? (isSaving ? '#16a34a' : '#0ea5e9') : '#0ea5e9';
                svgHtml += '<line x1="' + c1.x.toFixed(1) + '" y1="' + c1.y.toFixed(1) + '" x2="' + c2.x.toFixed(1) + '" y2="' + c2.y.toFixed(1) + '" stroke="' + strokeColor + '" stroke-width="3.5" stroke-linecap="round"/>';
            }

            // Points & Labels
            coords.forEach(function (c, idx) {
                const isLast = (idx === coords.length - 1);
                const isPrev = (idx === coords.length - 2);
                const dotColor = isLast ? '#16a34a' : (isPrev ? '#0284c7' : '#0ea5e9');

                // X-axis label (xlab)
                svgHtml += '<text x="' + c.x.toFixed(1) + '" y="' + (padT + plotH + 24) + '" text-anchor="middle" fill="#0f172a" font-size="12.5" font-weight="800">' + c.pt.xlab + '</text>';

                // Data circle
                svgHtml += '<circle cx="' + c.x.toFixed(1) + '" cy="' + c.y.toFixed(1) + '" r="6.5" fill="#ffffff" stroke="' + dotColor + '" stroke-width="3.5"/>';

                // Value pill above point
                const valText = formatTR(c.pt.consumption_m3, 1) + ' m³';
                const boxW = 64;
                const boxH = 22;
                const bx = c.x - boxW / 2;
                const by = c.y - 32;
                svgHtml += '<rect x="' + bx.toFixed(1) + '" y="' + by.toFixed(1) + '" width="' + boxW + '" height="' + boxH + '" rx="6" fill="#0f172a"/>';
                svgHtml += '<text x="' + c.x.toFixed(1) + '" y="' + (by + 15).toFixed(1) + '" text-anchor="middle" fill="#ffffff" font-size="11.5" font-weight="800">' + valText + '</text>';
            });

            svgChart.innerHTML = svgHtml;
        }

        function updateAnalyticsAndPond(withDrops) {
            const n = liveSeries.length;
            const prevPt = liveSeries[n - 2];
            const currPt = liveSeries[n - 1];
            const prevM3 = Number(prevPt.consumption_m3 || 0);
            const currM3 = Number(currPt.consumption_m3 || 0);

            // 1. Puanlama Yöntemi (Fatura Farkı -> Puan ve Gölet Birikimi)
            const diffM3 = +(prevM3 - currM3).toFixed(2);
            const tasarrufM3 = diffM3 > 0 ? diffM3 : 0;
            const kurtarilanLitre = Math.round(tasarrufM3 * 1000);
            const tasarrufYuzdesi = (tasarrufM3 > 0 && prevM3 > 0)
                ? +((tasarrufM3 / prevM3) * 100).toFixed(2)
                : 0;
            const kazanilanPuan = tasarrufYuzdesi >= 10.0 ? 10 : 0;

            if (kpiPrevLabel) {
                kpiPrevLabel.textContent = prevPt.xlab + (currentMode === 'yearly' ? ' Tüketimi' : ' Tüketimi (Geçen Ay)');
            }
            if (kpiCurrLabel) {
                kpiCurrLabel.textContent = currPt.xlab + (currentMode === 'yearly' ? ' Tüketimi' : ' Tüketimi (Bu Ay)');
            }
            if (kpiDiffLabel) {
                kpiDiffLabel.textContent = (currentMode === 'yearly' ? 'Yıllık' : 'Tasarruf') + ' Farkı & Oranı';
            }
            if (kpiPrevM3) kpiPrevM3.textContent = formatTR(prevM3, 1) + ' m³';
            if (kpiCurrM3) kpiCurrM3.textContent = formatTR(currM3, 1) + ' m³';
            if (kpiDiffM3) {
                if (tasarrufM3 > 0) {
                    kpiDiffM3.textContent = formatTR(tasarrufM3, 1) + ' m³ (' + formatTR(kurtarilanLitre, 0) + ' L · %' + formatTR(tasarrufYuzdesi, 1) + ')';
                    kpiDiffM3.style.color = 'var(--brand-green-dark)';
                } else {
                    kpiDiffM3.textContent = '0,0 m³ (0 L · %0,0)';
                    kpiDiffM3.style.color = diffM3 < 0 ? 'var(--brand-danger-dark)' : 'var(--text-main)';
                }
            }
            if (kpiEarnedPoints) {
                kpiEarnedPoints.textContent = kazanilanPuan > 0
                    ? '+' + kazanilanPuan + ' Puan 💧'
                    : '0 Puan';
                kpiEarnedPoints.style.color = kazanilanPuan > 0 ? 'var(--brand-green-dark)' : 'var(--text-main)';
            }

            // Ortak Gölet (Kolektif Havuz) Simülasyon & Gerçek Değer Hesabı
            const targetPondM3 = Math.max(1, Number(basePond.golet_hedef_m3 || 500));
            const baseOtherM3 = Math.max(0, Number(basePond.golet_mevcut_su_m3 || 0) - baseSavedM3);
            const baseOtherPts = Math.max(0, Number(basePond.golet_toplam_puan || 0) - baseEarnedPts);
            const baseOtherWallet = Math.max(0, baseWalletPts - baseEarnedPts);

            const livePondM3 = +(baseOtherM3 + tasarrufM3).toFixed(2);
            const livePondLiters = Math.round(livePondM3 * 1000);
            const livePondPts = baseOtherPts + kazanilanPuan;
            const liveWalletPts = baseOtherWallet + kazanilanPuan;
            const dolulukYuzdesi = Math.min(100, +((livePondM3 / targetPondM3) * 100).toFixed(1));

            if (pondCurrentM3Text) pondCurrentM3Text.textContent = formatTR(livePondM3, 2) + ' m³';
            if (pondCurrentLitersText) pondCurrentLitersText.textContent = formatTR(livePondLiters, 0) + ' L';
            if (pondTargetM3Text) pondTargetM3Text.textContent = formatTR(targetPondM3, 0) + ' m³';
            if (pondFillPctText) pondFillPctText.textContent = '%' + formatTR(dolulukYuzdesi, 1) + ' Doluluk';
            if (pondMeterFill) pondMeterFill.style.width = Math.min(100, Math.max(2, dolulukYuzdesi)) + '%';
            if (pondTotalPointsVal) pondTotalPointsVal.textContent = formatTR(livePondPts, 0) + ' Puan';
            if (userWalletPointsText) userWalletPointsText.textContent = formatTR(liveWalletPts, 0) + ' Debi Puanı';

            renderLineChart(liveSeries);

            // Gölet görsel aşaması (Kurumuş -> Yarı Dolu -> Tam Dolu)
            let stageKey = 'dry';
            if (dolulukYuzdesi >= 60 || kurtarilanLitre >= 3000) {
                stageKey = 'full';
            } else if (dolulukYuzdesi >= 20 || kurtarilanLitre >= 1000) {
                stageKey = 'half';
            }

            if (withDrops && tasarrufM3 > 0) {
                spawnFallingDrops(stageKey === 'full' ? 2 : 1);
            }

            if (stageKey !== currentStageKey) {
                currentStageKey = stageKey;
                pondImg.classList.add('pond-stage-switching');
                setTimeout(function () {
                    pondImg.src = analyticsCfg.images[stageKey] || analyticsCfg.images.dry;
                    pondImg.classList.remove('pond-stage-switching');
                }, 140);
            }
        }

        if (modeBtns.length > 0) {
            modeBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const targetMode = btn.getAttribute('data-chart-mode') || 'monthly';
                    if (targetMode === currentMode) return;
                    if (autoPlayTimer) {
                        clearInterval(autoPlayTimer);
                        autoPlayTimer = null;
                    }
                    currentMode = targetMode;
                    modeBtns.forEach(function (b) {
                        const isAct = (b.getAttribute('data-chart-mode') === currentMode);
                        b.classList.toggle('active', isAct);
                        b.classList.toggle('btn-primary', isAct);
                        b.classList.toggle('btn-outline', !isAct);
                    });
                    liveSeries = JSON.parse(JSON.stringify(currentMode === 'yearly' ? baseYearly : baseMonthly));
                    updateAnalyticsAndPond(true);
                });
            });
        }

        if (btnAutoPlay) {
            btnAutoPlay.addEventListener('click', function () {
                if (autoPlayTimer) {
                    clearInterval(autoPlayTimer);
                    autoPlayTimer = null;
                }
                const n = liveSeries.length;
                const basePrev = Math.max(12, liveSeries[n - 2].consumption_m3);
                liveSeries[n - 2].consumption_m3 = basePrev;
                liveSeries[n - 1].consumption_m3 = basePrev;
                updateAnalyticsAndPond(false);

                let steps = 0;
                autoPlayTimer = setInterval(function () {
                    steps++;
                    liveSeries[n - 1].consumption_m3 = Math.max(4, +(liveSeries[n - 1].consumption_m3 - 0.5).toFixed(2));
                    updateAnalyticsAndPond(true);
                    if (steps >= 7) {
                        clearInterval(autoPlayTimer);
                        autoPlayTimer = null;
                    }
                }, 650);
            });
        }

        if (btnResetReal) {
            btnResetReal.addEventListener('click', function () {
                if (autoPlayTimer) {
                    clearInterval(autoPlayTimer);
                    autoPlayTimer = null;
                }
                liveSeries = JSON.parse(JSON.stringify(currentMode === 'yearly' ? baseYearly : baseMonthly));
                const n = liveSeries.length;
                liveSeries[n - 1].consumption_m3 = liveSeries[n - 2].consumption_m3;
                updateAnalyticsAndPond(false);
            });
        }

        // İlk açılışta o ayın (Aylık) çizgi tablosunu ve göleti başlat
        const initSaved = Math.max(0, liveSeries[liveSeries.length - 2].consumption_m3 - liveSeries[liveSeries.length - 1].consumption_m3);
        updateAnalyticsAndPond(initSaved > 0);
    }
});
