/**
 * DEBI - İstemci Tarafı Etkileşimleri (Vanilla JS)
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-close-event-modal]').forEach(function (button) {
        button.addEventListener('click', function () {
            const modal = button.closest('.event-start-modal');
            if (modal) modal.remove();
        });
    });
    document.querySelectorAll('.event-photo-dropzone').forEach(function (dropzone) {
        const input = dropzone.querySelector('input[type="file"]');
        const name = dropzone.querySelector('.event-photo-file-name');
        if (!input || !name) return;
        const update = function () {
            const file = input.files && input.files[0];
            name.textContent = file ? file.name : 'Henüz fotoğraf seçilmedi';
            dropzone.classList.toggle('has-file', !!file);
        };
        input.addEventListener('change', update);
        ['dragenter', 'dragover'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropzone.classList.add('is-dragging');
            });
        });
        ['dragleave', 'drop'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropzone.classList.remove('is-dragging');
            });
        });
        dropzone.addEventListener('drop', function (event) {
            if (event.dataTransfer.files.length) {
                input.files = event.dataTransfer.files;
                update();
            }
        });
    });
    document.querySelectorAll('.notification-toggle').forEach(function (toggle) {
        const menu = toggle.closest('.notification-menu');
        const dropdown = menu ? menu.querySelector('.notification-dropdown') : null;
        if (!dropdown) return;
        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            const open = dropdown.hidden;
            document.querySelectorAll('.notification-dropdown').forEach(function (item) { item.hidden = true; });
            dropdown.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (event) {
            if (!menu.contains(event.target)) {
                dropdown.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    });

    // 1. Kayıt Sayfası Mod Seçimi (Bireysel / Davetli Daire / Apartman Yöneticisi)
    const modeTabs = document.querySelectorAll('.mode-tab[data-mode-target]');
    document.querySelectorAll('.mention-field').forEach(function (field) {
        const input = field.querySelector('textarea, input[type="text"]');
        const popup = field.querySelector('.mention-suggestions');
        if (!input || !popup) return;
        let users = [];
        let usersByScope = null;
        try {
            usersByScope = JSON.parse(field.getAttribute('data-mention-users-by-scope') || 'null');
            const parsedUsers = JSON.parse(field.getAttribute('data-mention-users') || '[]');
            users = Array.isArray(parsedUsers) ? parsedUsers : Object.values(parsedUsers || {});
        } catch (error) {
            users = [];
        }
        const visibility = field.closest('form')?.querySelector('[name="visibility"]');
        const refreshUsers = function () {
            if (usersByScope && visibility) users = usersByScope[visibility.value] || [];
        };
        if (visibility) visibility.addEventListener('change', refreshUsers);
        refreshUsers();
        let activeIndex = 0;
        const close = function () {
            popup.hidden = true;
            popup.innerHTML = '';
            activeIndex = 0;
        };
        const choose = function (user) {
            const value = input.value;
            const cursor = input.selectionStart || value.length;
            const before = value.slice(0, cursor);
            const match = before.match(/@([a-z0-9_]*)$/i);
            if (!match) return close();
            const start = cursor - match[0].length;
            input.value = value.slice(0, start) + '@' + user.nickname + '; ' + value.slice(cursor);
            const nextCursor = start + user.nickname.length + 3;
            input.setSelectionRange(nextCursor, nextCursor);
            input.focus();
            close();
        };
        const render = function (query) {
            const matches = users.filter(function (user) {
                return String(user.nickname).toLowerCase().indexOf(query.toLowerCase()) === 0;
            }).slice(0, 8);
            if (!matches.length) return close();
            popup.innerHTML = '';
            matches.forEach(function (user, index) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'mention-suggestion' + (index === 0 ? ' is-active' : '');
                button.dataset.nickname = user.nickname;
                const name = document.createElement('strong');
                name.textContent = user.full_name || user.nickname;
                button.appendChild(name);
                button.appendChild(document.createTextNode(' · @' + user.nickname));
                button.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    choose(user);
                });
                popup.appendChild(button);
            });
            activeIndex = 0;
            popup.hidden = false;
        };
        input.addEventListener('input', function () {
            const before = input.value.slice(0, input.selectionStart || input.value.length);
            const match = before.match(/@([a-z0-9_]*)$/i);
            if (match) render(match[1]);
            else close();
        });
        input.addEventListener('keydown', function (event) {
            if (popup.hidden) return;
            const options = popup.querySelectorAll('.mention-suggestion');
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                activeIndex = (activeIndex + (event.key === 'ArrowDown' ? 1 : options.length - 1)) % options.length;
                options.forEach(function (option, index) { option.classList.toggle('is-active', index === activeIndex); });
            } else if (event.key === 'Enter' || event.key === 'Tab') {
                event.preventDefault();
                const selectedNickname = options[activeIndex]?.dataset.nickname;
                const usersByNickname = users.find(function (user) {
                    return user.nickname === selectedNickname;
                });
                if (usersByNickname) choose(usersByNickname);
            } else if (event.key === 'Escape') {
                close();
            }
        });
        document.addEventListener('click', function (event) {
            if (!field.contains(event.target)) close();
        });
    });

    document.addEventListener('click', function (event) {
        const button = event.target.closest('.comment-reply-btn');
        if (!button) return;
        const item = button.closest('.comment-item');
        const form = item ? item.querySelector('.comment-reply-form') : null;
        if (!form) return;
        const shouldOpen = !form.classList.contains('is-open');
        document.querySelectorAll('.comment-reply-form').forEach(function (replyForm) {
            replyForm.hidden = true;
            replyForm.classList.remove('is-open');
        });
        document.querySelectorAll('.comment-reply-btn').forEach(function (replyButton) {
            replyButton.textContent = 'Yanıtla';
        });
        if (shouldOpen) {
            form.hidden = false;
            form.classList.add('is-open');
            button.textContent = 'Yanıtı kapat';
            const input = form.querySelector('[name="comment_content"]');
            if (input) {
                const nickname = button.dataset.replyNickname;
                const prefix = nickname ? '@' + nickname + '; ' : '';
                if (!input.value.trim() || !input.value.startsWith(prefix)) {
                    input.value = prefix;
                }
                input.focus();
                input.setSelectionRange(input.value.length, input.value.length);
            }
        }
    });

    document.querySelectorAll('.post-clickable').forEach(function (post) {
        post.addEventListener('click', function (event) {
            if (event.target.closest('a, button, form, input, textarea, select, label')) return;
            const url = post.dataset.postUrl;
            if (url) window.location.href = url;
        });
    });

    document.querySelectorAll('.comment-list').forEach(function (list) {
        const items = Array.from(list.children);
        const storageKey = 'debi-open-comment-thread-' + (list.dataset.postId || window.location.pathname);
        const byId = new Map(items.map(function (item) {
            return [item.dataset.commentId, item];
        }));
        const rootFor = function (item) {
            let current = item;
            const seen = new Set();
            while (current.dataset.parentCommentId !== '0' && !seen.has(current.dataset.commentId)) {
                seen.add(current.dataset.commentId);
                current = byId.get(current.dataset.parentCommentId) || current;
            }
            return current;
        };
        const descendantsOf = function (root) {
            return items.filter(function (item) {
                return item !== root && rootFor(item) === root;
            });
        };
        const roots = items.filter(function (item) {
            return item.dataset.parentCommentId === '0';
        });
        let openRoot = null;
        let savedRootId = null;
        try {
            savedRootId = window.localStorage.getItem(storageKey);
        } catch (error) {
            savedRootId = null;
        }
        const setRootOpen = function (root, open) {
            descendantsOf(root).forEach(function (item) {
                item.classList.toggle('comment-thread-collapsed', !open);
            });
            const toggle = root.querySelector('.comment-thread-toggle');
            if (toggle) {
                const label = toggle.querySelector('.comment-thread-label');
                const arrow = toggle.querySelector('.comment-thread-arrow');
                if (label) label.textContent = toggle.dataset.replyLabel;
                if (arrow) arrow.classList.toggle('is-open', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
            if (open) {
                openRoot = root;
                try {
                    window.localStorage.setItem(storageKey, root.dataset.commentId);
                } catch (error) {}
            } else if (openRoot === root) {
                openRoot = null;
                try {
                    window.localStorage.removeItem(storageKey);
                } catch (error) {}
            }
        };
        roots.forEach(function (root) {
            const descendants = descendantsOf(root);
            if (!descendants.length) return;
            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'comment-thread-toggle';
            toggle.dataset.replyLabel = `${descendants.length} yanıt`;
            toggle.innerHTML = '<span class="comment-thread-label"></span><span class="comment-thread-arrow" aria-hidden="true"></span>';
            toggle.querySelector('.comment-thread-label').textContent = toggle.dataset.replyLabel;
            toggle.setAttribute('aria-expanded', 'false');
            toggle.addEventListener('click', function () {
                const shouldOpen = descendants.some(function (item) {
                    return item.classList.contains('comment-thread-collapsed');
                });
                if (openRoot && openRoot !== root) setRootOpen(openRoot, false);
                setRootOpen(root, shouldOpen);
            });
            const content = root.querySelector('.comment-content');
            const replyButton = content ? content.querySelector('.comment-reply-btn') : null;
            const replyForm = content ? content.querySelector('.comment-reply-form') : null;
            if (content && replyButton) {
                const actions = document.createElement('div');
                actions.className = 'comment-actions';
                replyButton.replaceWith(actions);
                actions.appendChild(replyButton);
                actions.appendChild(toggle);
                if (replyForm) actions.after(replyForm);
            } else if (content) {
                content.appendChild(toggle);
            }
            setRootOpen(root, root.dataset.commentId === savedRootId);
        });

        items.forEach(function (item) {
            const paragraph = item.querySelector('.comment-content > p');
            if (!paragraph || paragraph.textContent.trim().length <= 280) return;
            paragraph.classList.add('comment-text-collapsed');
            const more = document.createElement('button');
            more.type = 'button';
            more.className = 'comment-text-toggle';
            more.textContent = 'Devamını oku';
            more.addEventListener('click', function () {
                const expanded = paragraph.classList.toggle('is-expanded');
                more.textContent = expanded ? 'Kısalt' : 'Devamını oku';
            });
            paragraph.insertAdjacentElement('afterend', more);
        });
    });
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
                    document.querySelectorAll('.alert').forEach(function (alert) {
                        window.setTimeout(function () {
                            alert.classList.add('is-dismissing');
                            window.setTimeout(function () {
                                if (alert.parentNode) alert.remove();
                            }, 220);
                        }, 5000);
                    });
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

            const actionUrl = form.getAttribute('action') || window.location.href;
            fetch(actionUrl, {
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
                            countSpan.textContent = data.count;
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

    // 9. Su Analizi Çizgi Tablosu (xlab / ylab)
    const analyticsCfg = window.DEBI_ANALYTICS_DATA;
    const svgChart = document.getElementById('waterLineChart');

    if (analyticsCfg && svgChart) {
        const baseMonthly = JSON.parse(JSON.stringify(analyticsCfg.series || []));
        const baseYearly = JSON.parse(JSON.stringify(analyticsCfg.yearlySeries || []));
        let currentMode = 'monthly';
        let liveSeries = JSON.parse(JSON.stringify(baseMonthly));

        const kpiPrevLabel = document.getElementById('kpiPrevLabel');
        const kpiCurrLabel = document.getElementById('kpiCurrLabel');
        const kpiDiffLabel = document.getElementById('kpiDiffLabel');
        const kpiPrevM3 = document.getElementById('kpiPrevM3');
        const kpiCurrM3 = document.getElementById('kpiCurrM3');
        const kpiDiffM3 = document.getElementById('kpiDiffM3');
        const kpiEarnedPoints = document.getElementById('kpiEarnedPoints');
        const modeBtns = document.querySelectorAll('.js-chart-mode');

        function formatTR(num, decimals) {
            return Number(num).toLocaleString('tr-TR', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
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
            const n = Array.isArray(dataPoints) ? dataPoints.length : 0;

            if (n === 0) {
                svgChart.innerHTML =
                    '<rect x="' + padL + '" y="' + padT + '" width="' + plotW + '" height="' + plotH + '" rx="12" fill="#f8fafc" stroke="#e2e8f0" stroke-dasharray="6 6" stroke-width="1.5"/>' +
                    '<text x="' + (W / 2) + '" y="' + (H / 2 - 4) + '" text-anchor="middle" fill="#334155" font-size="14" font-weight="800">Fatura verisi yok</text>' +
                    '<text x="' + (W / 2) + '" y="' + (H / 2 + 18) + '" text-anchor="middle" fill="#64748b" font-size="12" font-weight="600">Fatura yüklediğinizde grafik burada görüntülenir.</text>';
                return;
            }

            let maxM3 = 10;
            const minM3 = 0;
            dataPoints.forEach(function (pt) {
                if (pt.consumption_m3 > maxM3) maxM3 = pt.consumption_m3;
            });
            maxM3 = Math.ceil(maxM3 * 1.2);
            if (maxM3 % 2 !== 0) maxM3 += 1;

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
            const modeLabel = currentMode === 'yearly' ? 'Yıl' : 'Ay';
            svgHtml += '<text x="12" y="' + (padT + plotH / 2) + '" transform="rotate(-90 12 ' + (padT + plotH / 2) + ')" text-anchor="middle" fill="#334155" font-size="12" font-weight="800">m³</text>';
            svgHtml += '<text x="' + (padL + plotW / 2) + '" y="' + (H - 8) + '" text-anchor="middle" fill="#334155" font-size="12" font-weight="800">' + modeLabel + '</text>';

            // Tek veri noktası varsa yatay referans çizgisi ve bilgi notu göster
            if (coords.length === 1) {
                const c0 = coords[0];
                svgHtml += '<line x1="' + padL + '" y1="' + c0.y.toFixed(1) + '" x2="' + (W - padR) + '" y2="' + c0.y.toFixed(1) + '" stroke="#0ea5e9" stroke-dasharray="6 6" stroke-width="2" opacity="0.65"/>';
                svgHtml += '<text x="' + (padL + plotW / 2) + '" y="' + (padT + 14) + '" text-anchor="middle" fill="#64748b" font-size="11.5" font-weight="700">Tek fatura (' + c0.pt.period_label + ') · Karşılaştırma için 2. fatura bekleniyor</text>';
            }

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
                svgHtml += '<text x="' + c.x.toFixed(1) + '" y="' + (padT + plotH + 24) + '" text-anchor="middle" fill="#0f172a" font-size="12" font-weight="800">' + c.pt.xlab + '</text>';

                // Data circle
                svgHtml += '<circle cx="' + c.x.toFixed(1) + '" cy="' + c.y.toFixed(1) + '" r="6.5" fill="#ffffff" stroke="' + dotColor + '" stroke-width="3.5"/>';

                // Value pill above point
                const valText = formatTR(c.pt.consumption_m3, 1) + ' m³';
                const boxW = 68;
                const boxH = 22;
                const bx = c.x - boxW / 2;
                const by = Math.max(6, c.y - 32);
                svgHtml += '<rect x="' + bx.toFixed(1) + '" y="' + by.toFixed(1) + '" width="' + boxW + '" height="' + boxH + '" rx="6" fill="#0f172a"/>';
                svgHtml += '<text x="' + c.x.toFixed(1) + '" y="' + (by + 15).toFixed(1) + '" text-anchor="middle" fill="#ffffff" font-size="11.5" font-weight="800">' + valText + '</text>';
            });

            svgChart.innerHTML = svgHtml;
        }

        function updateAnalyticsView() {
            const n = Array.isArray(liveSeries) ? liveSeries.length : 0;
            let kazanilanDamla = 0;

            if (n >= 2) {
                const prevPt = liveSeries[n - 2];
                const currPt = liveSeries[n - 1];
                const prevM3 = Number(prevPt.consumption_m3 || 0);
                const currM3 = Number(currPt.consumption_m3 || 0);
                const diffM3 = +(prevM3 - currM3).toFixed(2);
                const tasarrufM3 = diffM3 > 0 ? diffM3 : 0;
                const kurtarilanLitre = Math.round(tasarrufM3 * 1000);
                const tasarrufYuzdesi = (tasarrufM3 > 0 && prevM3 > 0)
                    ? +((tasarrufM3 / prevM3) * 100).toFixed(1)
                    : 0;

                if (currentMode === 'monthly' && currPt.earned_points !== undefined) {
                    kazanilanDamla = Number(currPt.earned_points || 0);
                } else {
                    kazanilanDamla = (tasarrufM3 > 0 ? 10 : 0) + (tasarrufYuzdesi >= 10.0 ? 10 : 0);
                }

                if (kpiPrevLabel) kpiPrevLabel.textContent = prevPt.period_label || prevPt.xlab;
                if (kpiCurrLabel) kpiCurrLabel.textContent = currPt.period_label || currPt.xlab;
                if (kpiDiffLabel) kpiDiffLabel.textContent = currentMode === 'yearly' ? 'Yıllık Değişim' : 'Tasarruf';
                if (kpiPrevM3) kpiPrevM3.textContent = formatTR(prevM3, 1) + ' m³';
                if (kpiCurrM3) kpiCurrM3.textContent = formatTR(currM3, 1) + ' m³';

                if (kpiDiffM3) {
                    if (tasarrufM3 > 0) {
                        kpiDiffM3.textContent = formatTR(tasarrufM3, 1) + ' m³ (%' + formatTR(tasarrufYuzdesi, 1) + ')';
                        kpiDiffM3.style.color = 'var(--brand-green-dark)';
                    } else if (diffM3 < 0) {
                        kpiDiffM3.textContent = '+' + formatTR(Math.abs(diffM3), 1) + ' m³ artış';
                        kpiDiffM3.style.color = 'var(--brand-danger-dark)';
                    } else {
                        kpiDiffM3.textContent = 'Değişim yok';
                        kpiDiffM3.style.color = 'var(--text-main)';
                    }
                }
            } else if (n === 1) {
                const onlyPt = liveSeries[0];
                const onlyM3 = Number(onlyPt.consumption_m3 || 0);
                kazanilanDamla = currentMode === 'monthly' ? Number(onlyPt.earned_points || 0) : 0;

                if (kpiPrevLabel) kpiPrevLabel.textContent = currentMode === 'yearly' ? 'Önceki Yıl' : 'Önceki Fatura';
                if (kpiCurrLabel) kpiCurrLabel.textContent = onlyPt.period_label || onlyPt.xlab;
                if (kpiDiffLabel) kpiDiffLabel.textContent = 'Tasarruf';
                if (kpiPrevM3) kpiPrevM3.textContent = '—';
                if (kpiCurrM3) kpiCurrM3.textContent = formatTR(onlyM3, 1) + ' m³';
                if (kpiDiffM3) {
                    kpiDiffM3.textContent = currentMode === 'yearly' ? 'En az 2 yıl' : 'En az 2 fatura';
                    kpiDiffM3.style.color = 'var(--text-muted)';
                }
            } else {
                if (kpiPrevLabel) kpiPrevLabel.textContent = 'Önceki Fatura';
                if (kpiCurrLabel) kpiCurrLabel.textContent = 'Son Fatura';
                if (kpiDiffLabel) kpiDiffLabel.textContent = 'Tasarruf';
                if (kpiPrevM3) kpiPrevM3.textContent = '—';
                if (kpiCurrM3) kpiCurrM3.textContent = '—';
                if (kpiDiffM3) {
                    kpiDiffM3.textContent = 'Veri yok';
                    kpiDiffM3.style.color = 'var(--text-muted)';
                }
            }

            if (kpiEarnedPoints) {
                kpiEarnedPoints.textContent = kazanilanDamla > 0
                    ? '+' + kazanilanDamla + ' Damla 💧'
                    : '0 Damla';
                kpiEarnedPoints.style.color = kazanilanDamla > 0 ? 'var(--brand-green-dark)' : 'var(--text-main)';
            }

            renderLineChart(liveSeries);
        }

        if (modeBtns.length > 0) {
            modeBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const targetMode = btn.getAttribute('data-chart-mode') || 'monthly';
                    if (targetMode === currentMode) return;
                    currentMode = targetMode;
                    modeBtns.forEach(function (b) {
                        const isAct = (b.getAttribute('data-chart-mode') === currentMode);
                        b.classList.toggle('active', isAct);
                        b.classList.toggle('btn-primary', isAct);
                        b.classList.toggle('btn-outline', !isAct);
                    });
                    liveSeries = JSON.parse(JSON.stringify(currentMode === 'yearly' ? baseYearly : baseMonthly));
                    updateAnalyticsView();
                });
            });
        }

        updateAnalyticsView();
    }

    // 10. Yapay Zeka Fatura Doğrulama Canlı Kontrolü (water.php)
    const aiConfirmForm = document.getElementById('billConfirmForm');
    const aiRewardBanner = document.getElementById('aiVerifyRewardBanner');
    if (aiConfirmForm && aiRewardBanner && aiConfirmForm.getAttribute('data-ai-used') === '1') {
        const origMonth = String(aiConfirmForm.getAttribute('data-ai-month') || '');
        const origYear = String(aiConfirmForm.getAttribute('data-ai-year') || '');
        const origM3 = Number(String(aiConfirmForm.getAttribute('data-ai-m3') || '0').replace(',', '.'));
        const origDays = String(aiConfirmForm.getAttribute('data-ai-days') || '30');
        const origProvider = String(aiConfirmForm.getAttribute('data-ai-provider') || '').trim();
        const origBillRaw = String(aiConfirmForm.getAttribute('data-ai-bill') || '').trim();

        const inpMonth = document.getElementById('period_month');
        const inpYear = document.getElementById('period_year');
        const inpM3 = document.getElementById('consumption_m3');
        const inpDays = document.getElementById('reading_days');
        const inpProvider = document.getElementById('provider_name');
        const inpBill = document.getElementById('bill_amount');
        const aiVerifyIcon = document.getElementById('aiVerifyIcon');
        const aiVerifyText = document.getElementById('aiVerifyText');
        const submitBtn = document.getElementById('billConfirmSubmitBtn');

        function checkAiUnmodifiedState() {
            const curMonth = inpMonth ? String(inpMonth.value) : origMonth;
            const curYear = inpYear ? String(inpYear.value) : origYear;
            const curM3 = inpM3 ? Number(String(inpM3.value || '0').replace(',', '.')) : origM3;
            const curDays = inpDays ? String(inpDays.value) : origDays;
            const curProvider = inpProvider ? String(inpProvider.value || '').trim() : origProvider;
            const curBillRaw = inpBill ? String(inpBill.value || '').trim() : origBillRaw;

            const billMatches = (origBillRaw === '' && curBillRaw === '') ||
                (Math.abs(Number(curBillRaw.replace(',', '.')) - Number(origBillRaw.replace(',', '.'))) < 0.01);

            const isUnmodified = (
                curMonth === origMonth &&
                curYear === origYear &&
                Math.abs(curM3 - origM3) < 0.001 &&
                curDays === origDays &&
                curProvider === origProvider &&
                billMatches
            );

            aiRewardBanner.classList.toggle('is-modified', !isUnmodified);
            if (isUnmodified) {
                if (aiVerifyIcon) aiVerifyIcon.textContent = '✨';
                if (aiVerifyText) {
                    aiVerifyText.innerHTML = '<strong>AI değerleri değişmedi · Fatura otomatik puanlanacak</strong><small>Geçen aya göre azalma, sabitlik veya artış kuralı uygulanır.</small>';
                }
                if (submitBtn) {
                    submitBtn.innerHTML = '💧 Onayla &amp; Damla Kazan';
                }
            } else {
                if (aiVerifyIcon) aiVerifyIcon.textContent = '✏️';
                if (aiVerifyText) {
                    aiVerifyText.innerHTML = '<strong>AI değerleri değiştirildi · Admin incelemesi gerekli</strong><small>İnceleme tamamlanana kadar bu faturaya puan verilmez.</small>';
                }
                if (submitBtn) {
                    submitBtn.innerHTML = 'Faturayı Kaydet';
                }
            }
        }

        [inpMonth, inpYear, inpM3, inpDays, inpProvider, inpBill].forEach(function (el) {
            if (el) {
                el.addEventListener('input', checkAiUnmodifiedState);
                el.addEventListener('change', checkAiUnmodifiedState);
            }
        });
    }

    // 11. Ekranı Karartıp Gerçek Daire Göletine Damla Yağdıran Kutlama Modalı (water.php)
    const celData = window.DEBI_POND_CELEBRATION;
    const celOverlay = document.getElementById('pondCelebrationOverlay');
    if (celData && celOverlay) {
        const dropLayer = document.getElementById('pondCelDropLayer');
        const rippleRing = document.getElementById('pondCelRippleRing');
        const stageImg = document.getElementById('pondCelStageImg');
        const dropsCounter = document.getElementById('pondCelDropsCounter');
        const fillPctText = document.getElementById('pondCelFillPctText');
        const meterFill = document.getElementById('pondCelMeterFill');
        const closeBtn = document.getElementById('btnClosePondCelebration');

        const dropsCount = Math.min(24, Math.max(10, Number(celData.earnedDrops || 10)));
        const beforeDrops = Number(celData.dropsBefore || 0);
        const afterDrops = Number(celData.dropsAfter || beforeDrops + Number(celData.earnedDrops || 10));
        const beforeFill = Number(celData.fillBefore || 0);
        const afterFill = Number(celData.fillAfter || 0);

        // Damlaları yukarıdan gölete yağdır
        for (let i = 0; i < dropsCount; i++) {
            setTimeout(function () {
                if (!dropLayer) return;
                const drop = document.createElement('span');
                drop.className = 'falling-water-drop cel-water-drop';
                drop.textContent = '💧';
                const offset = (Math.random() * 190) - 95;
                drop.style.left = 'calc(50% + ' + Math.round(offset) + 'px)';
                dropLayer.appendChild(drop);

                setTimeout(function () {
                    if (rippleRing) {
                        rippleRing.classList.remove('ripple-active');
                        void rippleRing.offsetWidth;
                        rippleRing.classList.add('ripple-active');
                    }
                }, 540);

                setTimeout(function () {
                    if (drop.parentNode) {
                        drop.parentNode.removeChild(drop);
                    }
                }, 800);
            }, 180 + i * 115);
        }

        // Canlı Damla sayacı ve doluluk çubuğunu kademeli artır
        const animSteps = 20;
        let curStep = 0;
        const stepInterval = setInterval(function () {
            curStep++;
            const progress = Math.min(1, curStep / animSteps);
            const liveDrops = Math.round(beforeDrops + (afterDrops - beforeDrops) * progress);
            const liveFill = +(beforeFill + (afterFill - beforeFill) * progress).toFixed(0);

            if (dropsCounter) dropsCounter.textContent = String(liveDrops);
            if (fillPctText) fillPctText.textContent = '%' + String(liveFill) + ' Doluluk';
            if (meterFill) meterFill.style.width = Math.min(100, Math.max(3, liveFill)) + '%';

            // Gölet aşaması seviye atladıysa (ör. dry -> half veya half -> full) gerçek görseli değiştir
            if (progress >= 0.55 && celData.stageBefore !== celData.stageAfter && stageImg && !stageImg.dataset.switched) {
                stageImg.dataset.switched = '1';
                stageImg.classList.add('pond-stage-switching');
                setTimeout(function () {
                    if (celData.images && celData.images[celData.stageAfter]) {
                        stageImg.src = celData.images[celData.stageAfter];
                    }
                    stageImg.classList.remove('pond-stage-switching');
                }, 180);
            }

            if (curStep >= animSteps) {
                clearInterval(stepInterval);
            }
        }, 110);

        function closeCelebrationOverlay() {
            celOverlay.classList.add('is-closing');
            setTimeout(function () {
                if (celOverlay.parentNode) {
                    celOverlay.parentNode.removeChild(celOverlay);
                }
            }, 220);
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', closeCelebrationOverlay);
        }
        celOverlay.addEventListener('click', function (ev) {
            if (ev.target === celOverlay) {
                closeCelebrationOverlay();
            }
        });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && document.body.contains(celOverlay)) {
                closeCelebrationOverlay();
            }
        });
    }

    // 12. Sağ Sütun Debi Puan Sıralaması Sekmeleri (Arkadaş / Apartman / Genel)
    const lbTabs = document.querySelectorAll('.js-lb-tab');
    if (lbTabs.length > 0) {
        lbTabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                const targetId = tab.getAttribute('data-target');
                lbTabs.forEach(function (t) {
                    t.classList.toggle('active', t === tab);
                });
                document.querySelectorAll('.js-lb-panel').forEach(function (p) {
                    p.style.display = (p.id === targetId) ? '' : 'none';
                });
            });
        });
    }

    // 13. Oturum açılmış kullanıcı formlarında progressive AJAX gönderimi
    // Sunucu normal HTML redirect döndürmeye devam eder; yalnızca ana içerik yenilenir.
    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
        if ((form.method || 'get').toLowerCase() !== 'post') return;
        if (form.hasAttribute('data-no-ajax') || form.classList.contains('reaction-form')) return;

        const actionUrl = new URL(form.getAttribute('action') || window.location.href, window.location.href);
        const excludedPages = ['login.php', 'register.php', 'migration.php', 'migrate.php'];
        if (actionUrl.origin !== window.location.origin || excludedPages.indexOf(actionUrl.pathname.split('/').pop()) !== -1) return;

        const mainColumn = form.closest('.main-column');
        if (!mainColumn) return;

        event.preventDefault();
        const preservedFields = [];
        form.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (field) {
            if (field.type === 'file' || field.type === 'submit' || field.type === 'button') return;
            preservedFields.push({
                name: field.name,
                type: field.type,
                value: field.value,
                checked: field.checked
            });
        });
        const buttons = form.querySelectorAll('button, input[type="submit"]');
        const originalLabels = [];
        buttons.forEach(function (button) {
            originalLabels.push(button.textContent || button.value || '');
            button.disabled = true;
            if (button.tagName === 'BUTTON') button.textContent = 'Gönderiliyor...';
        });
        form.setAttribute('aria-busy', 'true');

        const showAjaxError = function (message) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-error ajax-form-error';
            alert.textContent = message;
            form.insertBefore(alert, form.firstChild);
            window.setTimeout(function () {
                alert.classList.add('is-dismissing');
                window.setTimeout(function () {
                    if (alert.parentNode) alert.remove();
                }, 220);
            }, 5000);
        };

        fetch(actionUrl.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.text().then(function (html) {
                    if (!response.ok) throw new Error('İşlem tamamlanamadı.');
                    return { html: html, url: response.url };
                });
            })
            .then(function (result) {
                const parser = new DOMParser();
                const documentFromResponse = parser.parseFromString(result.html, 'text/html');
                const updatedMain = documentFromResponse.querySelector('.main-column');
                if (!updatedMain) throw new Error('Sunucudan beklenen sayfa içeriği alınamadı.');

                const responseUrl = new URL(result.url, window.location.href);
                if (responseUrl.pathname !== window.location.pathname || responseUrl.search !== window.location.search) {
                    window.history.replaceState({}, '', responseUrl.href);
                }
                const currentTopbarPoints = document.querySelector('.topbar-drop-count');
                const updatedTopbarPoints = documentFromResponse.querySelector('.topbar-drop-count');
                if (currentTopbarPoints && updatedTopbarPoints) {
                    currentTopbarPoints.textContent = updatedTopbarPoints.textContent;
                }
                const responseFlash = documentFromResponse.querySelector('.flash-wrapper');
                if (responseFlash) {
                    const inlineFlash = responseFlash.querySelector('.alert');
                    if (inlineFlash) {
                        const existingFlash = updatedMain.querySelector('.ajax-form-error, .ajax-form-success');
                        if (existingFlash) existingFlash.remove();
                        const flashAlert = inlineFlash.cloneNode(true);
                        flashAlert.classList.add(inlineFlash.classList.contains('alert-error') ? 'ajax-form-error' : 'ajax-form-success');
                        updatedMain.insertBefore(flashAlert, updatedMain.firstChild);
                        window.setTimeout(function () {
                            flashAlert.classList.add('is-dismissing');
                            window.setTimeout(function () {
                                if (flashAlert.parentNode) flashAlert.remove();
                            }, 220);
                        }, 5000);
                    }
                }
                mainColumn.replaceWith(updatedMain);
                if (updatedMain.querySelector('.alert-error')) {
                    preservedFields.forEach(function (savedField) {
                        const matchingFields = updatedMain.querySelectorAll('[name="' + CSS.escape(savedField.name) + '"]');
                        if (savedField.type === 'checkbox' || savedField.type === 'radio') return;
                        matchingFields.forEach(function (field) {
                            if (field.type !== 'file') field.value = savedField.value;
                        });
                    });
                    preservedFields.filter(function (savedField) {
                        return savedField.type === 'checkbox' || savedField.type === 'radio';
                    }).forEach(function (savedField) {
                        const matchingFields = updatedMain.querySelectorAll('[name="' + CSS.escape(savedField.name) + '"]');
                        matchingFields.forEach(function (field) {
                            field.checked = preservedFields.some(function (candidate) {
                                return candidate.name === savedField.name
                                    && candidate.type === savedField.type
                                    && candidate.checked
                                    && candidate.value === field.value;
                            });
                        });
                    });
                }
                document.dispatchEvent(new CustomEvent('debi:content-updated'));
            })
            .catch(function (error) {
                buttons.forEach(function (button, index) {
                    button.disabled = false;
                    if (button.tagName === 'BUTTON') {
                        button.textContent = originalLabels[index];
                    }
                });
                form.removeAttribute('aria-busy');
                showAjaxError(error.message || 'İşlem sırasında bir hata oluştu. Lütfen tekrar deneyin.');
            });
    });
});
