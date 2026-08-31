import axios from 'axios';
import L from 'leaflet';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet/dist/leaflet.css';

// Leaflet's default marker image URLs are not resolved reliably by Vite unless
// the assets are imported explicitly. Without this, tiles can render while the
// pin itself is invisible.
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconUrl: markerIcon,
    iconRetinaUrl: markerIcon2x,
    shadowUrl: markerShadow,
});

window.axios = axios;
window.L = L;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
if (csrf) window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf;

const root = document.documentElement;
const authenticatedUserId = document.querySelector('meta[name="authenticated-user"]')?.content || '';
const authenticated = Boolean(authenticatedUserId);
function effectiveTheme(pref) {
    if (pref === 'system') return matchMedia('(prefers-color-scheme:dark)').matches ? 'dark' : 'light';
    return pref || 'light';
}
function syncThemeControls(pref) {
    const label = pref === 'system' ? 'Sistem' : pref === 'dark' ? 'Gelap' : 'Terang';
    const descriptions = {
        light: 'Selalu gunakan tampilan terang.',
        dark: 'Selalu gunakan tampilan gelap.',
        system: 'Ikuti pengaturan tampilan perangkat Anda.',
    };
    document.querySelectorAll('[data-theme-label]').forEach(x => x.textContent = label);
    document.querySelectorAll('[data-theme-choice]').forEach(button => {
        const active = button.dataset.themeChoice === pref;
        button.classList.toggle('active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    document.querySelectorAll('[data-theme-description]').forEach(x => x.textContent = descriptions[pref] || descriptions.system);
}
window.setSirkelTheme = (pref, persist = true) => {
    if (!['light','dark','system'].includes(pref)) pref = 'system';
    try {
        localStorage.setItem('sirkel-theme', pref);
        if (authenticatedUserId) localStorage.setItem(`sirkel-theme-user-${authenticatedUserId}`, pref);
    } catch (error) {}
    root.dataset.theme = effectiveTheme(pref);
    root.dataset.userTheme = pref;
    syncThemeControls(pref);
    if (persist && authenticated) axios.post('/theme', {theme: pref}).catch(() => {});
};
window.cycleSirkelTheme = () => {
    const now = root.dataset.userTheme || localStorage.getItem('sirkel-theme') || 'system';
    setSirkelTheme(now === 'light' ? 'dark' : now === 'dark' ? 'system' : 'light');
};
// Akun login memakai preference yang tersimpan di server sebagai sumber utama.
// Guest memakai localStorage supaya pilihan tetap tersimpan tanpa akun.
const initialTheme = authenticated
    ? (root.dataset.userTheme || 'system')
    : (localStorage.getItem('sirkel-theme') || root.dataset.userTheme || 'system');
setSirkelTheme(initialTheme, false);
matchMedia('(prefers-color-scheme:dark)').addEventListener('change', () => {
    const current = root.dataset.userTheme || 'system';
    if (current === 'system') setSirkelTheme('system', false);
});

window.confirmWhatsapp = (formId, phoneId) => {
    const form = document.getElementById(formId), phone = document.getElementById(phoneId), modal = document.getElementById('wa-confirm');
    if (!form || !phone || !modal) return;
    document.getElementById('wa-preview').textContent = phone.value || '-';
    modal.classList.add('show');
    modal.querySelector('[data-confirm]').onclick = () => { modal.classList.remove('show'); form.submit(); };
};
window.closeModal = id => document.getElementById(id)?.classList.remove('show');

const maps = new Map();
let generatedMapIdSequence = 0;
window.initSirkelMap = (mapId, latId, lngId, options = {}) => {
    const el = document.getElementById(mapId);
    if (!el || maps.has(mapId)) return maps.get(mapId);
    const latInput = document.getElementById(latId), lngInput = document.getElementById(lngId);
    const lat = Number(latInput?.value || options.lat || -7.2575), lng = Number(lngInput?.value || options.lng || 112.7521);
    const map = L.map(el, {scrollWheelZoom: false}).setView([lat, lng], options.zoom || 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'}).addTo(map);
    const marker = L.marker([lat, lng], {draggable: options.draggable !== false}).addTo(map);
    const update = point => {
        const normalized = {lat: Number(point.lat), lng: Number(point.lng)};
        if (latInput) latInput.value = normalized.lat.toFixed(7);
        if (lngInput) lngInput.value = normalized.lng.toFixed(7);
        marker.setLatLng(normalized);
        el.dispatchEvent(new CustomEvent('sirkel:map-point-changed', {detail: normalized}));
    };
    marker.on('dragend', e => update(e.target.getLatLng()));
    if (options.draggable !== false) map.on('click', e => update(e.latlng));
    const state = {map, marker, update}; maps.set(mapId, state); setTimeout(() => map.invalidateSize(), 100);
    return state;
};
window.getMyLocation = (latId, lngId, labelId, mapId = null) => {
    if (!navigator.geolocation) { alert('Browser tidak mendukung geolocation.'); return; }
    const label = labelId ? document.getElementById(labelId) : null;
    if (label) label.textContent = 'Mengambil lokasi...';
    navigator.geolocation.getCurrentPosition(pos => {
        const point = {lat: pos.coords.latitude, lng: pos.coords.longitude};
        const latInput = document.getElementById(latId);
        const lngInput = document.getElementById(lngId);
        if (latInput) latInput.value = point.lat.toFixed(7);
        if (lngInput) lngInput.value = point.lng.toFixed(7);
        if (label) label.textContent = 'Lokasi berhasil diambil · akurasi ±' + Math.round(pos.coords.accuracy) + ' m';
        if (mapId) {
            const state = maps.get(mapId) || initSirkelMap(mapId, latId, lngId, {lat: point.lat, lng: point.lng, zoom: 15});
            state?.update(point);
            state?.map.setView([point.lat, point.lng], 15);
        }
    }, () => { if (label) label.textContent = 'Lokasi belum tersedia'; alert('Lokasi tidak dapat diambil. Pastikan izin lokasi aktif.'); }, {enableHighAccuracy: true, timeout: 10000, maximumAge: 15000});
};


// v1.0.28 — consistent custom select UI. Native <select> remains the form source of
// truth, while the visible menu avoids full-screen mobile pickers. Search is opt-in
// via data-searchable="true" for long datasets such as the electronics catalogue.
const sirkelSelectInstances = new WeakMap();
function selectOptionRows(select) {
    const rows = [];
    [...select.children].forEach(child => {
        if (child instanceof HTMLOptGroupElement) {
            rows.push({kind: 'group', label: child.label, key: `group-${rows.length}`});
            [...child.children].forEach(option => rows.push({kind: 'option', option, group: child.label}));
        } else if (child instanceof HTMLOptionElement) {
            rows.push({kind: 'option', option: child, group: ''});
        }
    });
    return rows;
}

function initSirkelSelect(select) {
    if (!(select instanceof HTMLSelectElement) || select.multiple || Number(select.size || 0) > 1 || select.dataset.nativeSelect === 'true') return null;
    if (sirkelSelectInstances.has(select)) return sirkelSelectInstances.get(select);

    const wrapper = document.createElement('div');
    wrapper.className = 'sirkel-select';
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);
    select.classList.add('sirkel-native-select');

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'sirkel-select-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    const triggerText = document.createElement('span');
    triggerText.className = 'sirkel-select-trigger-text';
    const chevron = document.createElement('span');
    chevron.className = 'sirkel-select-chevron';
    chevron.setAttribute('aria-hidden', 'true');
    trigger.append(triggerText, chevron);

    const backdrop = document.createElement('button');
    backdrop.type = 'button';
    backdrop.className = 'sirkel-select-backdrop';
    backdrop.tabIndex = -1;
    backdrop.setAttribute('aria-label', 'Tutup pilihan');
    backdrop.hidden = true;

    const menu = document.createElement('div');
    menu.className = 'sirkel-select-menu';
    menu.hidden = true;

    const searchable = select.dataset.searchable === 'true';
    let search = null;
    if (searchable) {
        const searchWrap = document.createElement('div');
        searchWrap.className = 'sirkel-select-search-wrap';
        search = document.createElement('input');
        search.type = 'search';
        search.className = 'sirkel-select-search';
        search.placeholder = select.dataset.searchPlaceholder || 'Cari pilihan...';
        search.setAttribute('autocomplete', 'off');
        searchWrap.appendChild(search);
        menu.appendChild(searchWrap);
    }

    const optionsBox = document.createElement('div');
    optionsBox.className = 'sirkel-select-options';
    optionsBox.setAttribute('role', 'listbox');
    menu.appendChild(optionsBox);
    wrapper.append(trigger, backdrop, menu);

    function syncTrigger() {
        const selected = select.options[select.selectedIndex];
        triggerText.textContent = selected?.textContent?.trim() || select.dataset.placeholder || 'Pilih salah satu';
        trigger.disabled = select.disabled;
        trigger.classList.toggle('is-placeholder', !select.value);
    }

    function render(filter = '') {
        const term = String(filter || '').trim().toLocaleLowerCase('id-ID');
        optionsBox.replaceChildren();
        const rows = selectOptionRows(select);
        let currentGroup = null;
        let groupEl = null;
        let visibleInGroup = 0;
        let totalVisible = 0;

        const finalizeGroup = () => {
            if (groupEl && visibleInGroup === 0) groupEl.remove();
        };

        rows.forEach(row => {
            if (row.kind === 'group') {
                finalizeGroup();
                currentGroup = row.label;
                visibleInGroup = 0;
                groupEl = document.createElement('div');
                groupEl.className = 'sirkel-select-group';
                groupEl.textContent = row.label;
                optionsBox.appendChild(groupEl);
                return;
            }
            const option = row.option;
            const label = option.textContent.trim();
            const haystack = `${label} ${row.group || ''}`.toLocaleLowerCase('id-ID');
            if (term && !haystack.includes(term)) return;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'sirkel-select-option';
            button.dataset.value = option.value;
            button.setAttribute('role', 'option');
            button.setAttribute('aria-selected', option.selected ? 'true' : 'false');
            button.disabled = option.disabled;
            button.textContent = label;
            if (!option.value) button.classList.add('is-placeholder');
            if (option.selected) button.classList.add('is-selected');
            button.addEventListener('click', () => {
                if (option.disabled) return;
                select.value = option.value;
                select.dispatchEvent(new Event('change', {bubbles: true}));
                syncTrigger();
                close();
            });
            optionsBox.appendChild(button);
            totalVisible += 1;
            if (currentGroup) visibleInGroup += 1;
        });
        finalizeGroup();
        if (!totalVisible) {
            const empty = document.createElement('div');
            empty.className = 'sirkel-select-empty';
            empty.textContent = 'Pilihan tidak ditemukan.';
            optionsBox.appendChild(empty);
        }
    }

    function open() {
        if (select.disabled) return;
        document.querySelectorAll('.sirkel-select.is-open').forEach(other => {
            if (other !== wrapper) other.querySelector('.sirkel-select-trigger')?.click();
        });
        wrapper.classList.add('is-open');
        menu.hidden = false;
        backdrop.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        document.body.classList.add('sirkel-select-open');
        render(search?.value || '');
        if (search) window.setTimeout(() => search.focus(), 0);
    }

    function close() {
        wrapper.classList.remove('is-open');
        menu.hidden = true;
        backdrop.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        if (!document.querySelector('.sirkel-select.is-open')) document.body.classList.remove('sirkel-select-open');
        if (search) search.value = '';
    }

    trigger.addEventListener('click', () => wrapper.classList.contains('is-open') ? close() : open());
    backdrop.addEventListener('click', close);
    search?.addEventListener('input', () => render(search.value));
    select.addEventListener('change', syncTrigger);
    select.addEventListener('focus', () => trigger.focus());
    wrapper.addEventListener('keydown', event => {
        if (event.key === 'Escape') { close(); trigger.focus(); }
    });

    const observer = new MutationObserver(() => {
        syncTrigger();
        if (wrapper.classList.contains('is-open')) render(search?.value || '');
    });
    observer.observe(select, {childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'selected', 'label']});

    const api = {wrapper, trigger, menu, render, syncTrigger, close};
    sirkelSelectInstances.set(select, api);
    syncTrigger();
    return api;
}

window.refreshSirkelSelect = selectOrId => {
    const select = typeof selectOrId === 'string' ? document.getElementById(selectOrId) : selectOrId;
    if (!select) return;
    const api = sirkelSelectInstances.get(select) || initSirkelSelect(select);
    api?.syncTrigger();
    if (api?.wrapper.classList.contains('is-open')) api.render();
};

document.addEventListener('click', event => {
    document.querySelectorAll('.sirkel-select.is-open').forEach(wrapper => {
        if (!wrapper.contains(event.target)) wrapper.querySelector('.sirkel-select-trigger')?.click();
    });
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('select.select').forEach(initSirkelSelect);
});

window.previewFiles = (input, targetId) => {
    const target = document.getElementById(targetId); if (!target) return;
    target.innerHTML = '';
    [...(input.files || [])].slice(0, 3).forEach(file => {
        const img = document.createElement('img'); img.src = URL.createObjectURL(file); img.alt = 'Preview foto'; target.appendChild(img);
    });
};

// v1.0.31 — photo picker + opt-in AI intake assistant.
// Photos remain local to the form unless the citizen explicitly presses
// "Proses dengan AI". AI suggestions never submit the form automatically.
const assetPhotoPickerStates = new WeakMap();
let activeAssetCameraState = null;
let activeAssetCameraStream = null;

function setAssetPhotoFiles(input, files) {
    const transfer = new DataTransfer();
    files.forEach(file => transfer.items.add(file));
    input.files = transfer.files;
}

function assetPhotoMessage(state, message = '', tone = '') {
    if (!state?.photoStatus) return;
    state.photoStatus.textContent = message;
    state.photoStatus.dataset.tone = tone;
}

function assetAiMessage(state, message = '', tone = '') {
    if (!state?.aiStatus) return;
    state.aiStatus.textContent = message;
    state.aiStatus.dataset.tone = tone;
}

function invalidateAssetAiSuggestion(state) {
    state.suggestion = null;
    if (state.scopeInput) state.scopeInput.value = 'unknown';
    state.aiRevision += 1;
    // Jika foto berubah saat request AI masih berjalan, hasil request lama menjadi
    // stale. UI langsung kembali bisa dipakai tanpa menunggu request tersebut selesai.
    state.processing = false;
    if (state.aiButton) {
        state.aiButton.disabled = state.files.length === 0 || state.quotaExhausted;
        state.aiButton.textContent = state.quotaExhausted ? 'Kuota habis' : 'Proses dengan AI';
    }
    if (state.quotaExhausted) {
        assetAiMessage(state, 'Kuota Pengenalan Barang sudah habis. Tambah kuota dari menu Kuota AI jika ingin memakai bantuan foto.', 'warning');
    } else if (state.files.length > 0) {
        assetAiMessage(state, 'Belum meminta bantuan AI untuk foto ini. Foto tetap siap digunakan di formulir.', 'muted');
    } else if (!state.processing) {
        assetAiMessage(state, 'Tambahkan foto jika ingin menggunakan bantuan AI.', 'muted');
    }
}

function renderAssetPhotoSelection(state) {
    if (!state?.preview) return;
    state.preview.replaceChildren();

    state.files.forEach((file, index) => {
        const card = document.createElement('div');
        card.className = 'asset-photo-preview-card';
        const image = document.createElement('img');
        const url = URL.createObjectURL(file);
        image.src = url;
        image.alt = `Preview foto ${index + 1}`;
        image.onload = () => URL.revokeObjectURL(url);

        const badge = document.createElement('span');
        badge.className = 'asset-photo-index';
        badge.textContent = index === 0 ? 'Utama' : String(index + 1);

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'asset-photo-remove';
        remove.setAttribute('aria-label', `Hapus foto ${index + 1}`);
        remove.textContent = '×';
        remove.addEventListener('click', () => {
            state.files.splice(index, 1);
            if (state.files.length <= 1) state.scopeNoticeShown = false;
            setAssetPhotoFiles(state.input, state.files);
            renderAssetPhotoSelection(state);
            invalidateAssetAiSuggestion(state);
            assetPhotoMessage(state, state.files.length ? `${state.files.length} foto dipilih.` : 'Belum ada foto dipilih.', state.files.length ? 'success' : 'muted');
        });

        card.append(image, badge, remove);
        state.preview.appendChild(card);
    });

    if (state.aiButton) state.aiButton.disabled = state.files.length === 0 || state.processing || state.quotaExhausted;
}

function sameAssetFile(a, b) {
    return a?.name === b?.name && a?.size === b?.size && a?.lastModified === b?.lastModified;
}

async function addAssetPhotoFiles(state, rawFiles) {
    const accepted = ['image/jpeg', 'image/png', 'image/webp'];
    const maxBytes = state.maxSizeMb * 1024 * 1024;
    let ignored = 0;
    let tooLarge = 0;

    for (const file of rawFiles) {
        if (!file) continue;
        if (!accepted.includes(file.type)) {
            ignored += 1;
            continue;
        }
        if (file.size > maxBytes) {
            tooLarge += 1;
            continue;
        }
        if (state.files.some(existing => sameAssetFile(existing, file))) continue;
        if (state.files.length >= state.maxFiles) {
            ignored += 1;
            continue;
        }
        state.files.push(file);
    }

    setAssetPhotoFiles(state.input, state.files);
    renderAssetPhotoSelection(state);
    invalidateAssetAiSuggestion(state);

    const notes = [];
    if (state.files.length) notes.push(`${state.files.length} foto dipilih.`);
    else notes.push('Belum ada foto yang dapat digunakan.');
    if (tooLarge) notes.push(`${tooLarge} foto melebihi ${state.maxSizeMb} MB.`);
    if (ignored) notes.push(`Maksimal ${state.maxFiles} foto JPG, PNG, atau WebP.`);
    assetPhotoMessage(state, notes.join(' '), tooLarge || ignored ? 'warning' : 'success');
    showAssetPhotoScopeNotice(state);
}

function closeAssetPhotoScopeNotice() {
    const modal = document.querySelector('[data-asset-photo-scope-modal]');
    if (!modal) return;
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
}

function showAssetPhotoScopeNotice(state) {
    if (!state || state.files.length <= 1 || state.scopeNoticeShown) return;
    const modal = document.querySelector('[data-asset-photo-scope-modal]');
    if (!modal) return;
    state.scopeNoticeShown = true;
    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');
}

function closeAssetCamera() {
    activeAssetCameraStream?.getTracks?.().forEach(track => track.stop());
    activeAssetCameraStream = null;
    activeAssetCameraState = null;
    const modal = document.querySelector('[data-asset-camera-modal]');
    const video = modal?.querySelector('[data-asset-camera-video]');
    if (video) video.srcObject = null;
    if (modal) {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
    }
}

async function openAssetCamera(state) {
    if (!state || state.files.length >= state.maxFiles) {
        assetPhotoMessage(state, `Maksimal ${state?.maxFiles || 3} foto. Hapus salah satu foto jika ingin mengambil foto baru.`, 'warning');
        return;
    }

    const modal = document.querySelector('[data-asset-camera-modal]');
    const video = modal?.querySelector('[data-asset-camera-video]');
    const cameraState = modal?.querySelector('[data-asset-camera-state]');
    if (!modal || !video || !navigator.mediaDevices?.getUserMedia) {
        state.cameraFallback?.click();
        return;
    }

    activeAssetCameraState = state;
    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');
    if (cameraState) cameraState.textContent = 'Meminta izin kamera...';

    try {
        activeAssetCameraStream = await navigator.mediaDevices.getUserMedia({
            video: {facingMode: {ideal: 'environment'}},
            audio: false,
        });
        video.srcObject = activeAssetCameraStream;
        await video.play();
        if (cameraState) cameraState.textContent = 'Kamera siap. Arahkan ke barang lalu ambil foto.';
    } catch (error) {
        closeAssetCamera();
        state.cameraFallback?.click();
    }
}

function closeAssetAiModal() {
    const modal = document.querySelector('[data-asset-ai-modal]');
    if (!modal) return;
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
    modal._assetPhotoState = null;
}

function normalizeCompareText(value) {
    return String(value || '').trim().toLocaleLowerCase('id-ID');
}

function assetFormContext() {
    return {
        category: document.getElementById('device-category'),
        customName: document.getElementById('custom-item-name'),
        tracking: document.getElementById('tracking-type'),
        quantity: document.getElementById('asset-quantity'),
        description: document.querySelector('#asset-create-form [name="description"]'),
    };
}

function describeAiTracking(suggestion) {
    if (suggestion.tracking_type === 'batch') return 'Kelompok barang';
    return 'Satuan';
}

function assetAiAvailableFields(modal) {
    return [...modal.querySelectorAll('[data-ai-field-card]')]
        .filter(card => !card.hidden)
        .map(card => card.dataset.aiFieldCard)
        .filter(Boolean);
}

function updateAssetAiSelectionUi(modal) {
    const selected = modal._assetAiSelectedFields instanceof Set ? modal._assetAiSelectedFields : new Set();
    const available = assetAiAvailableFields(modal);

    available.forEach(key => {
        const active = selected.has(key);
        const toggle = modal.querySelector(`[data-ai-field-toggle="${key}"]`);
        const status = modal.querySelector(`[data-ai-field-status="${key}"]`);
        const action = modal.querySelector(`[data-ai-field-action="${key}"]`);
        const card = modal.querySelector(`[data-ai-field-card="${key}"]`);
        toggle?.setAttribute('aria-pressed', active ? 'true' : 'false');
        card?.classList.toggle('selected', active);
        if (status) status.textContent = active ? 'Dipilih' : 'Belum dipilih';
        if (action) action.textContent = active ? 'Batalkan' : 'Pilih';
    });

    const count = modal.querySelector('[data-asset-ai-selected-count]');
    if (count) count.textContent = selected.size ? `${selected.size} saran dipilih.` : 'Belum ada saran dipilih.';

    const selectAll = modal.querySelector('[data-asset-ai-select-all]');
    if (selectAll) selectAll.textContent = available.length > 0 && selected.size === available.length ? 'Batalkan semua' : 'Pilih semua';

    const apply = modal.querySelector('[data-asset-ai-apply]');
    if (apply) apply.disabled = selected.size === 0;
}

function toggleAssetAiField(modal, key) {
    if (!(modal._assetAiSelectedFields instanceof Set)) modal._assetAiSelectedFields = new Set();
    if (!key) return;
    if (modal._assetAiSelectedFields.has(key)) modal._assetAiSelectedFields.delete(key);
    else modal._assetAiSelectedFields.add(key);
    updateAssetAiSelectionUi(modal);
}

function showAssetAiSuggestion(state, suggestion) {
    const modal = document.querySelector('[data-asset-ai-modal]');
    if (!modal) return;
    modal._assetPhotoState = state;
    state.suggestion = suggestion;
    modal._assetAiSelectedFields = new Set();

    const resultGrid = modal.querySelector('[data-asset-ai-result-grid]');
    const rejection = modal.querySelector('[data-asset-ai-rejection]');
    const reviewNote = modal.querySelector('[data-asset-ai-review-note]');
    const selectActions = modal.querySelector('[data-asset-ai-select-actions]');
    const replace = modal.querySelector('[data-asset-ai-replace]');
    const apply = modal.querySelector('[data-asset-ai-apply]');
    const difference = modal.querySelector('[data-asset-ai-difference]');
    const keep = modal.querySelector('[data-asset-ai-keep]');
    const bulk = modal.querySelector('[data-asset-ai-bulk]');
    const rejectionTitle = modal.querySelector('[data-asset-ai-rejection-title]');

    if (suggestion?.eligible === false) {
        if (resultGrid) resultGrid.hidden = true;
        if (rejection) rejection.hidden = false;
        if (reviewNote) reviewNote.hidden = true;
        if (selectActions) selectActions.hidden = true;
        if (apply) apply.hidden = true;
        if (replace) replace.hidden = false;
        if (bulk) bulk.hidden = !suggestion.requires_bulk;
        if (rejectionTitle) rejectionTitle.textContent = suggestion.requires_bulk
            ? 'Beberapa jenis barang terdeteksi'
            : 'Foto belum sesuai untuk bantuan AI';
        if (difference) {
            difference.hidden = true;
            difference.textContent = '';
        }
        if (keep) {
            keep.hidden = Boolean(suggestion.requires_bulk);
            keep.textContent = 'Isi manual';
        }

        const reason = modal.querySelector('[data-asset-ai-rejection-reason]');
        const detected = modal.querySelector('[data-asset-ai-rejection-detected]');
        if (reason) {
            reason.textContent = suggestion.eligibility_reason
                || 'Gunakan foto barang elektronik fisik yang ingin didaftarkan.';
        }
        if (detected) {
            detected.textContent = suggestion.detected_name
                ? `Yang terbaca dari foto: ${suggestion.detected_name}.`
                : '';
        }

        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        return;
    }

    if (resultGrid) resultGrid.hidden = false;
    if (rejection) rejection.hidden = true;
    if (reviewNote) reviewNote.hidden = false;
    if (selectActions) selectActions.hidden = false;
    if (apply) apply.hidden = false;
    if (replace) replace.hidden = true;
    if (bulk) bulk.hidden = true;
    if (keep) keep.hidden = false;
    if (rejectionTitle) rejectionTitle.textContent = 'Foto belum sesuai untuk bantuan AI';

    const context = assetFormContext();
    const currentCategory = context.category?.options?.[context.category.selectedIndex];
    const hasUserData = Boolean(
        context.category?.value
        || normalizeCompareText(context.customName?.value)
        || normalizeCompareText(context.description?.value)
        || context.tracking?.value === 'batch'
        || Number(context.quantity?.value || 1) > 1
    );

    const differences = [];
    if (context.category?.value && String(context.category.value) !== String(suggestion.category_id)) differences.push('jenis barang');
    if (suggestion.requires_custom_name && normalizeCompareText(context.customName?.value) && normalizeCompareText(context.customName.value) !== normalizeCompareText(suggestion.custom_item_name)) differences.push('nama barang');
    if (hasUserData && context.tracking?.value && context.tracking.value !== suggestion.tracking_type) differences.push('tipe pencatatan');
    if (context.tracking?.value === 'batch' && Number.isInteger(suggestion.visible_quantity) && Number(context.quantity?.value || 0) !== Number(suggestion.visible_quantity)) differences.push('jumlah yang terlihat');
    if (normalizeCompareText(context.description?.value) && suggestion.description && normalizeCompareText(context.description.value) !== normalizeCompareText(suggestion.description)) differences.push('kondisi singkat');

    const name = modal.querySelector('[data-ai-result-name]');
    const category = modal.querySelector('[data-ai-result-category]');
    const tracking = modal.querySelector('[data-ai-result-tracking]');
    const quantity = modal.querySelector('[data-ai-result-quantity]');
    const description = modal.querySelector('[data-ai-result-description]');
    const descriptionCard = modal.querySelector('[data-ai-field-card="description"]');

    if (name) name.textContent = suggestion.custom_item_name || suggestion.detected_name || suggestion.category_name || 'Belum dapat dipastikan';
    if (category) {
        category.textContent = suggestion.group_name
            ? `${suggestion.category_name} · ${suggestion.group_name}`
            : suggestion.category_name;
    }
    if (tracking) tracking.textContent = describeAiTracking(suggestion);
    if (quantity) {
        if (Number.isInteger(suggestion.visible_quantity)) {
            quantity.textContent = suggestion.visible_quantity > 1
                ? `Dari foto terlihat ${suggestion.visible_quantity} barang.`
                : 'Dari foto terlihat 1 barang.';
        } else {
            quantity.textContent = 'Jumlah tidak dapat dipastikan dari foto.';
        }
        if (suggestion.visible_quantity > 1 && suggestion.tracking_type !== 'batch') {
            quantity.textContent += suggestion.supports_batch
                ? ' AI belum cukup yakin semua barang tersebut sejenis untuk dicatat sebagai kelompok.'
                : ' Kategori ini tetap dicatat satuan di SIRKEL.';
        }
    }
    if (description) description.textContent = suggestion.description || 'Tidak ada kondisi visual yang cukup jelas untuk disarankan.';
    if (descriptionCard) descriptionCard.hidden = !suggestion.description;

    if (difference) {
        if (suggestion.needs_electronic_confirmation && suggestion.eligibility_reason) {
            difference.hidden = false;
            difference.textContent = suggestion.eligibility_reason;
        } else if (differences.length) {
            difference.hidden = false;
            difference.textContent = `Foto memberi saran berbeda pada ${differences.join(', ')}. Pilih saran yang memang ingin Anda terapkan.`;
        } else if (hasUserData && currentCategory) {
            difference.hidden = false;
            difference.textContent = 'Saran dari foto sejalan dengan data yang sudah Anda isi. Anda tetap bebas memilih saran mana yang ingin digunakan.';
        } else {
            difference.hidden = true;
            difference.textContent = '';
        }
    }
    if (keep) keep.textContent = hasUserData ? 'Tetap gunakan data saya' : 'Isi sendiri';

    updateAssetAiSelectionUi(modal);
    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');
}

function applyAssetAiSuggestion(state) {
    const suggestion = state?.suggestion;
    const modal = document.querySelector('[data-asset-ai-modal]');
    const selected = modal?._assetAiSelectedFields instanceof Set ? modal._assetAiSelectedFields : new Set();
    if (!suggestion || suggestion.eligible === false || selected.size === 0) return;
    const context = assetFormContext();

    if (selected.has('identity') && context.category && suggestion.category_id) {
        context.category.value = String(suggestion.category_id);
        context.category.dispatchEvent(new Event('change', {bubbles: true}));
        window.refreshSirkelSelect?.(context.category);

        if (context.customName && suggestion.requires_custom_name && suggestion.custom_item_name) {
            context.customName.value = suggestion.custom_item_name;
            context.customName.dispatchEvent(new Event('input', {bubbles: true}));
        }
    }

    if (selected.has('tracking') && context.tracking && suggestion.tracking_type) {
        context.tracking.value = suggestion.tracking_type;
        context.tracking.dispatchEvent(new Event('change', {bubbles: true}));
        window.refreshSirkelSelect?.(context.tracking);

        if (context.quantity) {
            context.quantity.value = suggestion.tracking_type === 'batch' && Number(suggestion.visible_quantity) >= 2
                ? String(suggestion.visible_quantity)
                : '1';
            context.quantity.dispatchEvent(new Event('input', {bubbles: true}));
        }
    }

    if (selected.has('description') && context.description && suggestion.description) {
        context.description.value = suggestion.description;
        context.description.dispatchEvent(new Event('input', {bubbles: true}));
    }

    const appliedCount = selected.size;
    closeAssetAiModal();
    assetAiMessage(
        state,
        `${appliedCount} saran sudah digunakan. Periksa kembali data sebelum melanjutkan.`,
        'success'
    );
    document.getElementById('device-category')?.scrollIntoView({behavior: 'smooth', block: 'center'});
}

async function processAssetPhotosWithAi(state) {
    if (!state || state.processing || !state.files.length) return;
    if (state.suggestion) {
        assetAiMessage(state, 'Saran sebelumnya ditampilkan kembali. Tidak ada kuota tambahan yang digunakan.', 'muted');
        showAssetAiSuggestion(state, state.suggestion);
        return;
    }
    if (state.quotaExhausted) {
        assetAiMessage(state, 'Kuota Pengenalan Barang sudah habis. Tambah kuota dari menu Kuota AI.', 'warning');
        return;
    }
    state.processing = true;
    const revision = ++state.aiRevision;
    state.aiButton.disabled = true;
    state.aiButton.textContent = 'Memproses...';
    assetAiMessage(state, 'AI sedang memeriksa foto yang Anda pilih...', 'muted');

    try {
        const body = new FormData();
        state.files.forEach(file => body.append('photos[]', file));
        const {data} = await axios.post(state.aiUrl, body);
        if (revision !== state.aiRevision) return;
        if (data?.quota) {
            state.quotaRemaining = Number(data.quota.remaining || 0);
            state.quotaExhausted = Boolean(data.quota.exhausted) || state.quotaRemaining <= 0;
            const label = state.picker.querySelector('[data-asset-ai-quota-label]');
            if (label) label.textContent = `${state.quotaRemaining}×`;
        }
        if (!data?.suggestion) throw new Error('Bantuan AI belum menghasilkan saran yang dapat digunakan.');
        if (state.scopeInput) state.scopeInput.value = data.suggestion.scope_status || (data.suggestion.eligible === false ? 'unknown' : 'single_type');
        if (data.suggestion.eligible === false) {
            assetAiMessage(
                state,
                data.suggestion.requires_bulk
                    ? 'Beberapa jenis barang terdeteksi. Gunakan Bulk AI atau pilih foto yang hanya menampilkan satu jenis barang.'
                    : 'Foto berhasil diperiksa, tetapi belum dapat digunakan sebagai saran barang elektronik.',
                'warning'
            );
        } else {
            assetAiMessage(state, 'Pemeriksaan selesai. Data formulir belum berubah; pilih saran yang ingin Anda gunakan.', 'success');
        }
        showAssetAiSuggestion(state, data.suggestion);
    } catch (error) {
        if (revision !== state.aiRevision) return;
        const validation = error?.response?.data?.errors;
        const firstValidation = validation && Object.values(validation).flat()?.[0];
        if (error?.response?.data?.quota_exhausted) {
            state.quotaRemaining = 0;
            state.quotaExhausted = true;
            const label = state.picker.querySelector('[data-asset-ai-quota-label]');
            if (label) label.textContent = '0×';
        }
        const message = firstValidation || error?.response?.data?.message || error?.message || 'Bantuan AI belum berhasil.';
        assetAiMessage(state, message, 'warning');
    } finally {
        if (revision === state.aiRevision) {
            state.processing = false;
            state.aiButton.disabled = state.files.length === 0 || state.quotaExhausted;
            state.aiButton.textContent = state.quotaExhausted ? 'Kuota habis' : 'Proses dengan AI';
        }
    }
}

window.bindAssetPhotoAssistant = picker => {
    if (!(picker instanceof HTMLElement) || assetPhotoPickerStates.has(picker)) return assetPhotoPickerStates.get(picker);
    const input = picker.querySelector('[data-asset-photo-input]');
    const galleryButton = picker.querySelector('[data-asset-gallery]');
    const galleryInput = picker.querySelector('[data-asset-gallery-input]');
    const cameraButton = picker.querySelector('[data-asset-camera]');
    const cameraFallback = picker.querySelector('[data-asset-camera-fallback]');
    const preview = picker.querySelector('[data-asset-photo-preview]');
    const photoStatus = picker.querySelector('[data-asset-photo-status]');
    const aiButton = picker.querySelector('[data-asset-ai-process]');
    const aiStatus = picker.querySelector('[data-asset-ai-status]');
    const scopeInput = picker.closest('form')?.querySelector('[data-asset-photo-scope-status]');
    if (!input || !galleryButton || !cameraButton || !preview || !aiButton) return null;

    const state = {
        picker,
        input,
        galleryButton,
        galleryInput,
        cameraButton,
        cameraFallback,
        preview,
        photoStatus,
        aiButton,
        aiStatus,
        scopeInput,
        aiUrl: picker.dataset.aiUrl,
        maxFiles: Math.max(1, Number(picker.dataset.maxFiles || 3)),
        maxSizeMb: Math.max(1, Number(picker.dataset.maxSizeMb || 5)),
        files: Array.from(input.files || []),
        suggestion: null,
        aiRevision: 0,
        processing: false,
        scopeNoticeShown: false,
        quotaRemaining: Math.max(0, Number(picker.dataset.aiQuotaRemaining || 0)),
        quotaExhausted: Number(picker.dataset.aiQuotaRemaining || 0) <= 0,
        topupUrl: picker.dataset.aiTopupUrl || '',
    };
    assetPhotoPickerStates.set(picker, state);

    galleryButton.addEventListener('click', () => galleryInput?.click());
    galleryInput?.addEventListener('change', async () => {
        const chosen = Array.from(galleryInput.files || []);
        galleryInput.value = '';
        await addAssetPhotoFiles(state, chosen);
    });
    cameraButton.addEventListener('click', () => openAssetCamera(state));
    cameraFallback?.addEventListener('change', async () => {
        const chosen = Array.from(cameraFallback.files || []);
        cameraFallback.value = '';
        await addAssetPhotoFiles(state, chosen);
    });
    aiButton.addEventListener('click', () => processAssetPhotosWithAi(state));
    picker.closest('form')?.addEventListener('submit', event => {
        if (state.scopeInput?.value === 'multiple_types') {
            event.preventDefault();
            assetPhotoMessage(state, 'Beberapa jenis barang terdeteksi. Pendaftaran biasa hanya untuk satu jenis barang; pilih foto lain atau gunakan Bulk AI.', 'warning');
            picker.scrollIntoView({behavior: 'smooth', block: 'center'});
            return;
        }
        if (state.files.length > 0) return;
        event.preventDefault();
        assetPhotoMessage(state, 'Tambahkan minimal 1 foto sebelum menyimpan barang.', 'warning');
        picker.scrollIntoView({behavior: 'smooth', block: 'center'});
        galleryButton.focus();
    });

    setAssetPhotoFiles(input, state.files);
    renderAssetPhotoSelection(state);
    invalidateAssetAiSuggestion(state);
    assetPhotoMessage(state, state.files.length ? `${state.files.length} foto dipilih.` : 'Belum ada foto dipilih.', 'muted');
    return state;
};

window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-asset-photo-picker]').forEach(picker => window.bindAssetPhotoAssistant(picker));

    const scopeModal = document.querySelector('[data-asset-photo-scope-modal]');
    scopeModal?.querySelectorAll('[data-asset-photo-scope-close]').forEach(button => button.addEventListener('click', closeAssetPhotoScopeNotice));
    scopeModal?.addEventListener('click', event => { if (event.target === scopeModal) closeAssetPhotoScopeNotice(); });

    const cameraModal = document.querySelector('[data-asset-camera-modal]');
    cameraModal?.querySelectorAll('[data-asset-camera-close]').forEach(button => button.addEventListener('click', closeAssetCamera));
    cameraModal?.addEventListener('click', event => { if (event.target === cameraModal) closeAssetCamera(); });
    cameraModal?.querySelector('[data-asset-camera-capture]')?.addEventListener('click', async () => {
        const state = activeAssetCameraState;
        const video = cameraModal.querySelector('[data-asset-camera-video]');
        if (!state || !video?.videoWidth || !video?.videoHeight) return;
        const canvas = document.createElement('canvas');
        const scale = Math.min(1, 2000 / Math.max(video.videoWidth, video.videoHeight));
        canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
        canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
        canvas.getContext('2d')?.drawImage(video, 0, 0, video.videoWidth, video.videoHeight, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.9));
        closeAssetCamera();
        if (!blob) return;
        const file = new File([blob], `kamera-${Date.now()}.jpg`, {type: 'image/jpeg', lastModified: Date.now()});
        await addAssetPhotoFiles(state, [file]);
    });

    const aiModal = document.querySelector('[data-asset-ai-modal]');
    aiModal?.querySelectorAll('[data-asset-ai-close], [data-asset-ai-keep]').forEach(button => button.addEventListener('click', closeAssetAiModal));
    aiModal?.addEventListener('click', event => { if (event.target === aiModal) closeAssetAiModal(); });
    aiModal?.querySelectorAll('[data-ai-field-toggle]').forEach(button => button.addEventListener('click', () => {
        toggleAssetAiField(aiModal, button.dataset.aiFieldToggle);
    }));
    aiModal?.querySelector('[data-asset-ai-select-all]')?.addEventListener('click', () => {
        const available = assetAiAvailableFields(aiModal);
        if (!(aiModal._assetAiSelectedFields instanceof Set)) aiModal._assetAiSelectedFields = new Set();
        if (available.length > 0 && aiModal._assetAiSelectedFields.size === available.length) {
            aiModal._assetAiSelectedFields.clear();
        } else {
            aiModal._assetAiSelectedFields = new Set(available);
        }
        updateAssetAiSelectionUi(aiModal);
    });
    aiModal?.querySelector('[data-asset-ai-apply]')?.addEventListener('click', () => {
        const state = aiModal._assetPhotoState;
        if (state) applyAssetAiSuggestion(state);
    });
    aiModal?.querySelector('[data-asset-ai-replace]')?.addEventListener('click', () => {
        const state = aiModal._assetPhotoState;
        closeAssetAiModal();
        state?.galleryInput?.click();
    });
});

window.addEventListener('beforeunload', closeAssetCamera);


function appendSafeInlineMarkdown(parent, text) {
    const source = String(text || '');
    const pattern = /(\*\*[^*\n]+\*\*|`[^`\n]+`|\*[^*\n]+\*)/g;
    let cursor = 0;
    for (const match of source.matchAll(pattern)) {
        if (match.index > cursor) parent.append(document.createTextNode(source.slice(cursor, match.index)));
        const token = match[0];
        let node;
        if (token.startsWith('**')) {
            node = document.createElement('strong');
            node.textContent = token.slice(2, -2);
        } else if (token.startsWith('`')) {
            node = document.createElement('code');
            node.textContent = token.slice(1, -1);
        } else {
            node = document.createElement('em');
            node.textContent = token.slice(1, -1);
        }
        parent.append(node);
        cursor = match.index + token.length;
    }
    if (cursor < source.length) parent.append(document.createTextNode(source.slice(cursor)));
}

window.renderSafeMarkdown = function renderSafeMarkdown(target, markdown) {
    if (!target) return;
    const text = String(markdown || '').replace(/\r\n?/g, '\n').trim();
    target.replaceChildren();
    if (!text) return;

    const lines = text.split('\n');
    let list = null;
    let listType = '';
    const closeList = () => { list = null; listType = ''; };

    lines.forEach(rawLine => {
        const line = rawLine.trim();
        if (!line) { closeList(); return; }
        const unordered = line.match(/^[-*]\s+(.+)$/);
        const ordered = line.match(/^\d+[.)]\s+(.+)$/);
        if (unordered || ordered) {
            const type = ordered ? 'ol' : 'ul';
            if (!list || listType !== type) {
                list = document.createElement(type);
                list.className = 'rich-ai-list';
                target.append(list);
                listType = type;
            }
            const item = document.createElement('li');
            appendSafeInlineMarkdown(item, (unordered || ordered)[1]);
            list.append(item);
            return;
        }
        closeList();
        const paragraph = document.createElement('p');
        appendSafeInlineMarkdown(paragraph, line.replace(/^#{1,4}\s+/, ''));
        target.append(paragraph);
    });
};

function questionBasicHelp(field) {
    const questionType = field?.dataset.questionType || 'single';
    const seededHelp = String(field?.dataset.helpText || '').trim();
    return seededHelp || (
        questionType === 'text'
            ? 'Tuliskan informasi yang benar-benar Anda ketahui dengan bahasa sederhana. Tidak perlu memakai istilah teknis. Jika pertanyaan ini tidak wajib dan Anda tidak memiliki informasi tambahan, kolom dapat dikosongkan.'
            : questionType === 'multi'
                ? 'Anda dapat memilih lebih dari satu kondisi yang sesuai. Pilih hanya kondisi yang benar-benar Anda ketahui.'
                : 'Pilih jawaban yang paling sesuai dengan kondisi yang benar-benar Anda ketahui. Jika tersedia pilihan “Tidak tahu” dan Anda belum yakin, gunakan pilihan tersebut.'
    );
}

function closeQuestionHelpModal() {
    const modal = document.querySelector('[data-question-help-modal]');
    if (!modal) return;
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
}

function openQuestionHelpModal(button) {
    const field = button?.closest('[data-assessment-question]');
    const modal = document.querySelector('[data-question-help-modal]');
    if (!field || !modal) return;
    const title = modal.querySelector('[data-question-help-title]');
    const copy = modal.querySelector('[data-question-help-copy]');
    if (title) title.textContent = field.dataset.questionText || 'Pertanyaan cek kondisi';
    if (copy) copy.textContent = questionBasicHelp(field);
    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');
}

function assessmentQuestionHasValue(field) {
    const type = field.dataset.questionType || 'single';
    if (type === 'text') return Boolean(field.querySelector('textarea')?.value?.trim());
    return Boolean(field.querySelector('input:checked'));
}

function assessmentAnswersPayload(form) {
    const payload = {};
    form.querySelectorAll('[data-assessment-question]').forEach(field => {
        const code = field.dataset.questionCode;
        const type = field.dataset.questionType || 'single';
        if (!code) return;
        if (type === 'text') {
            const value = field.querySelector('textarea')?.value?.trim() || '';
            if (value) payload[code] = value;
            return;
        }
        const checked = [...field.querySelectorAll('input:checked')].map(input => input.value);
        if (!checked.length) return;
        payload[code] = type === 'multi' ? checked : checked[0];
    });
    return payload;
}

function bindCitizenAssessmentAi(form) {
    if (!form) return;
    const button = form.querySelector('[data-generate-condition-description]');
    const status = form.querySelector('[data-condition-ai-status]');
    const notes = form.querySelector('[data-condition-notes]');
    if (!button || !notes) return;

    const requiredFields = [...form.querySelectorAll('[data-assessment-question][data-question-required="1"]')]
        .filter(field => field.dataset.questionCode !== 'notes');
    let quotaRemaining = Math.max(0, Number(form.dataset.aiDescriptionQuota || 0));
    let quotaExhausted = quotaRemaining <= 0;
    const quotaLabel = form.querySelector('[data-condition-ai-quota-label]');

    const updateAvailability = () => {
        const complete = requiredFields.every(assessmentQuestionHasValue);
        button.disabled = !complete || quotaExhausted;
        if (status && !button.dataset.loading) {
            if (quotaExhausted) {
                status.textContent = 'Kuota Penyusunan Catatan Kondisi sudah habis. Anda tetap dapat menulis catatan secara manual atau menambah kuota.';
                status.dataset.tone = 'warning';
            } else {
                status.textContent = complete
                    ? `Semua pertanyaan wajib sudah terisi. AI dapat membantu menyusun catatan kondisi. Sisa kuota ${quotaRemaining}×.`
                    : 'Lengkapi semua pertanyaan wajib untuk mengaktifkan bantuan AI.';
                status.dataset.tone = complete ? 'success' : '';
            }
        }
    };

    form.addEventListener('change', updateAvailability);
    form.addEventListener('input', updateAvailability);
    updateAvailability();

    button.addEventListener('click', async () => {
        updateAvailability();
        if (button.disabled || button.dataset.loading) return;
        button.dataset.loading = '1';
        button.disabled = true;
        const original = button.innerHTML;
        button.textContent = 'Menyusun deskripsi...';
        if (status) {
            status.textContent = 'Sedang menyiapkan deskripsi...';
            status.dataset.tone = '';
        }

        try {
            const {data} = await axios.post(form.dataset.aiDescriptionUrl, {answers: assessmentAnswersPayload(form)});
            if (data?.quota) {
                quotaRemaining = Math.max(0, Number(data.quota.remaining || 0));
                quotaExhausted = Boolean(data.quota.exhausted) || quotaRemaining <= 0;
                if (quotaLabel) quotaLabel.textContent = `${quotaRemaining}×`;
            }
            notes.value = String(data.description || '').trim();
            notes.dispatchEvent(new Event('input', {bubbles: true}));
            notes.focus({preventScroll: true});
            notes.scrollIntoView({behavior: 'smooth', block: 'center'});
            if (status) {
                status.textContent = 'Deskripsi sudah ditambahkan. Periksa kembali jika ingin mengubahnya.';
                status.dataset.tone = 'success';
            }
        } catch (error) {
            if (error?.response?.data?.quota_exhausted) {
                quotaRemaining = 0;
                quotaExhausted = true;
                if (quotaLabel) quotaLabel.textContent = '0×';
            }
            if (status) {
                status.textContent = error?.response?.data?.message || 'Deskripsi AI belum dapat dibuat. Anda tetap dapat menulis kondisi secara manual.';
                status.dataset.tone = 'warning';
            }
        } finally {
            delete button.dataset.loading;
            button.innerHTML = original;
            updateAvailability();
        }
    });
}

window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-open-question-help]').forEach(button => {
        button.addEventListener('click', () => openQuestionHelpModal(button));
    });
    const helpModal = document.querySelector('[data-question-help-modal]');
    helpModal?.querySelectorAll('[data-question-help-close]').forEach(button => button.addEventListener('click', closeQuestionHelpModal));
    helpModal?.addEventListener('click', event => { if (event.target === helpModal) closeQuestionHelpModal(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeQuestionHelpModal(); });
    document.querySelectorAll('[data-citizen-assessment-form]').forEach(bindCitizenAssessmentAi);
});

window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-auto-map]').forEach(el => initSirkelMap(el.id, el.dataset.latInput, el.dataset.lngInput, {lat: el.dataset.lat, lng: el.dataset.lng, zoom: Number(el.dataset.zoom || 13), draggable: el.dataset.readonly !== 'true'}));
    document.querySelectorAll('[data-map-link-picker]').forEach(el => window.bindMapLinkPicker(el));
});

window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-ai-markdown]').forEach(el => renderSafeMarkdown(el, el.textContent));
});

const regionVillageCache = new Map();

function setVillagePlaceholder(select, text, disabled = true) {
    select.replaceChildren(new Option(text, ''));
    select.disabled = disabled;
    select.setAttribute('aria-busy', text === 'Memuat kelurahan...' ? 'true' : 'false');
    window.refreshSirkelSelect?.(select);
}

async function getVillagesForDistrict(district) {
    if (regionVillageCache.has(district)) return regionVillageCache.get(district);

    const request = axios.get('/regions/villages', {params: {district}})
        .then(({data}) => {
            if (!Array.isArray(data)) throw new Error('Respons wilayah tidak valid');
            return data
                .map(item => String(item?.name ?? '').trim())
                .filter(Boolean);
        })
        .catch(error => {
            regionVillageCache.delete(district);
            throw error;
        });

    regionVillageCache.set(district, request);
    return request;
}

window.bindRegionSelect = (districtId, villageId, selectedVillage = '') => {
    const district = document.getElementById(districtId);
    const village = document.getElementById(villageId);
    if (!district || !village) return;

    let initialVillage = String(selectedVillage || '').trim();
    let requestNumber = 0;

    const load = async () => {
        const currentRequest = ++requestNumber;
        const selectedDistrict = String(district.value || '').trim();

        if (!selectedDistrict) {
            setVillagePlaceholder(village, 'Pilih kecamatan terlebih dahulu');
            return;
        }

        setVillagePlaceholder(village, 'Memuat kelurahan...');

        try {
            const names = await getVillagesForDistrict(selectedDistrict);
            if (currentRequest !== requestNumber) return;

            if (!names.length) {
                setVillagePlaceholder(village, 'Data kelurahan belum tersedia. Hubungi admin.');
                return;
            }

            village.replaceChildren(new Option('Pilih kelurahan', ''));
            names.forEach(name => village.add(new Option(name, name)));
            village.disabled = false;
            village.setAttribute('aria-busy', 'false');

            window.refreshSirkelSelect?.(village);

            if (initialVillage) {
                const hasSavedVillage = names.includes(initialVillage);
                if (hasSavedVillage) {
                    village.value = initialVillage;
                } else {
                    // Legacy data is shown instead of silently disappearing.
                    const legacy = new Option(`${initialVillage} (data lama — pilih ulang)`, initialVillage, true, true);
                    village.add(legacy);
                    window.refreshSirkelSelect?.(village);
                }
            }
        } catch (error) {
            if (currentRequest !== requestNumber) return;
            console.error('Gagal memuat kelurahan:', error);
            setVillagePlaceholder(village, 'Kelurahan gagal dimuat. Klik untuk coba lagi.', false);
        }
    };

    village.addEventListener('focus', () => {
        if (!village.disabled && village.options.length === 1 && district.value) load();
    });
    village.addEventListener('click', () => {
        if (village.options.length === 1 && district.value && village.value === '') load();
    });
    district.addEventListener('change', () => {
        initialVillage = '';
        load();
    });

    load();
};


window.bindHandoverMethodForm = (form) => {
    if (!form) return;
    const methods = [...form.querySelectorAll('input[name="method"]')];
    const addressSection = form.querySelector('[data-pickup-address-section]');
    const address = form.querySelector('[data-pickup-address]');
    const title = form.querySelector('[data-location-title]');
    const help = form.querySelector('[data-location-help]');
    const privacy = form.querySelector('[data-method-privacy-note]');
    const dateLabel = form.querySelector('[data-date-label]');
    const timeLabel = form.querySelector('[data-time-label]');

    const refresh = () => {
        const method = methods.find(input => input.checked)?.value || 'pickup';
        const pickup = method === 'pickup';
        if (addressSection) addressSection.hidden = !pickup;
        if (address) {
            address.required = pickup;
            address.disabled = !pickup;
        }
        if (title) title.textContent = pickup ? 'Titik penjemputan *' : 'Lokasi awal untuk menghitung jarak *';
        if (help) help.textContent = pickup
            ? 'Pilih titik tempat Mitra akan mengambil barang.'
            : 'Titik ini hanya dipakai mengurutkan mitra berdasarkan jarak. Mitra tidak menerima alamat rumah Anda sebagai lokasi penyerahan.';
        if (privacy) privacy.textContent = pickup
            ? 'Untuk penjemputan, alamat lengkap baru terlihat oleh mitra setelah permintaan diterima.'
            : 'Untuk antar langsung, Anda datang ke lokasi mitra. SIRKEL hanya menggunakan titik ini untuk perkiraan jarak rekomendasi.';
        if (dateLabel) dateLabel.textContent = pickup ? 'Tanggal penjemputan yang diinginkan *' : 'Rencana tanggal mengantar *';
        if (timeLabel) timeLabel.textContent = pickup ? 'Rentang waktu penjemputan *' : 'Perkiraan waktu mengantar';
    };

    methods.forEach(input => input.addEventListener('change', refresh));
    refresh();
};

function mapsUrlForPoint(lat, lng) {
    return `https://www.google.com/maps/search/?api=1&query=${Number(lat).toFixed(7)},${Number(lng).toFixed(7)}`;
}

window.bindMapLinkPicker = (root) => {
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const mapEl = root.querySelector('[data-picker-map]') || root.querySelector('[data-auto-map]') || root.querySelector('[data-location-map]');
    if (mapEl && !mapEl.id) {
        generatedMapIdSequence += 1;
        mapEl.id = `sirkel-location-map-${generatedMapIdSequence}`;
    }
    const mapId = root.dataset.mapId || mapEl?.id;
    const latId = root.dataset.latId || mapEl?.dataset.latInput;
    const lngId = root.dataset.lngId || mapEl?.dataset.lngInput;
    const labelId = root.dataset.labelId || '';
    const latInput = latId ? document.getElementById(latId) : null;
    const lngInput = lngId ? document.getElementById(lngId) : null;
    const generated = root.querySelector('[data-generated-map-link]');
    const input = root.querySelector('[data-map-link-input]');
    const resolveButton = root.querySelector('[data-resolve-map-link]');
    const status = root.querySelector('[data-map-link-status]');
    const copyButton = root.querySelector('[data-copy-map-link]');
    const tabs = [...root.querySelectorAll('[data-location-source], [data-location-mode]')];
    const panels = [...root.querySelectorAll('[data-location-panel]')];

    const syncGeneratedLink = () => {
        const lat = Number(latInput?.value);
        const lng = Number(lngInput?.value);
        if (!Number.isFinite(lat) || !Number.isFinite(lng) || !generated) return;
        const url = mapsUrlForPoint(lat, lng);
        generated.href = url;
        generated.dataset.mapsUrl = url;
    };

    const updateLabel = point => {
        const label = labelId ? document.getElementById(labelId) : null;
        if (label && point) label.textContent = `Titik dipilih · ${Number(point.lat).toFixed(6)}, ${Number(point.lng).toFixed(6)}`;
    };

    const selectSource = source => {
        tabs.forEach(tab => {
            const tabSource = tab.dataset.locationSource || tab.dataset.locationMode;
            const active = tabSource === source;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach(panel => panel.hidden = panel.dataset.locationPanel !== source);
        if (source === 'map') {
            window.setTimeout(() => mapId && maps.get(mapId)?.map.invalidateSize(), 60);
        } else {
            window.setTimeout(() => input?.focus(), 50);
        }
    };

    tabs.forEach(tab => tab.addEventListener('click', () => selectSource(tab.dataset.locationSource || tab.dataset.locationMode)));

    if (mapEl && mapId && latId && lngId && !maps.has(mapId)) {
        initSirkelMap(mapId, latId, lngId, {
            lat: mapEl.dataset.lat,
            lng: mapEl.dataset.lng,
            zoom: Number(mapEl.dataset.zoom || 15),
            draggable: mapEl.dataset.readonly !== 'true',
        });
    }

    mapEl?.addEventListener('sirkel:map-point-changed', event => {
        syncGeneratedLink();
        if (event.detail) updateLabel(event.detail);
    });

    resolveButton?.addEventListener('click', async () => {
        const url = String(input?.value || '').trim();
        if (!url) {
            if (status) status.textContent = 'Tempel link Google Maps terlebih dahulu.';
            input?.focus();
            return;
        }
        resolveButton.disabled = true;
        if (status) status.textContent = 'Membaca titik dari link Google Maps...';
        try {
            const {data} = await axios.post(root.dataset.resolveUrl, {url});
            const point = {lat: Number(data.latitude), lng: Number(data.longitude)};
            if (!Number.isFinite(point.lat) || !Number.isFinite(point.lng)) throw new Error('Koordinat tidak valid');
            if (latInput) latInput.value = point.lat.toFixed(7);
            if (lngInput) lngInput.value = point.lng.toFixed(7);
            if (input && data.maps_url) input.value = data.maps_url;
            if (mapId && latId && lngId) {
                const state = maps.get(mapId) || initSirkelMap(mapId, latId, lngId, {lat: point.lat, lng: point.lng, zoom: 15});
                state?.update(point);
                state?.map.setView([point.lat, point.lng], 16);
            }
            syncGeneratedLink();
            updateLabel(point);
            if (status) status.textContent = `Titik berhasil dibaca · ${point.lat.toFixed(6)}, ${point.lng.toFixed(6)}. Pin pada peta sudah dipindahkan.`;
            selectSource('map');
        } catch (error) {
            const message = error?.response?.data?.errors?.url?.[0]
                || error?.response?.data?.message
                || 'Koordinat belum dapat dibaca. Coba salin ulang link dari tombol Bagikan di Google Maps.';
            if (status) status.textContent = message;
        } finally {
            resolveButton.disabled = false;
        }
    });

    copyButton?.addEventListener('click', async () => {
        syncGeneratedLink();
        const url = generated?.dataset.mapsUrl;
        if (!url) return;
        try {
            await navigator.clipboard.writeText(url);
            const old = copyButton.textContent;
            copyButton.textContent = 'Tersalin';
            window.setTimeout(() => copyButton.textContent = old, 1200);
        } catch {
            if (status) status.textContent = 'Link belum dapat disalin otomatis. Buka link lalu salin dari browser.';
        }
    });

    input?.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            resolveButton?.click();
        }
    });

    let pasteTimer = null;
    input?.addEventListener('paste', () => {
        window.clearTimeout(pasteTimer);
        pasteTimer = window.setTimeout(() => resolveButton?.click(), 120);
    });

    syncGeneratedLink();
};



function setExpanded(button, expanded) {
    if (button) button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
}

window.togglePublicMenu = (force = null) => {
    const menu = document.getElementById('public-mobile-menu');
    const button = document.querySelector('[data-public-menu-toggle]');
    const backdrop = document.querySelector('[data-public-menu-backdrop]');
    if (!menu) return;
    const open = force === null ? !menu.classList.contains('is-open') : Boolean(force);
    menu.classList.toggle('is-open', open);
    backdrop?.classList.toggle('is-open', open);
    document.body.classList.toggle('public-menu-open', open);
    menu.setAttribute('aria-hidden', open ? 'false' : 'true');
    setExpanded(button, open);
};

window.toggleAppMenu = (force = null) => {
    const sidebar = document.getElementById('app-sidebar');
    const button = document.querySelector('[data-app-menu-toggle]');
    const backdrop = document.querySelector('[data-app-menu-backdrop]');
    if (!sidebar) return;
    const open = force === null ? !sidebar.classList.contains('is-open') : Boolean(force);
    sidebar.classList.toggle('is-open', open);
    backdrop?.classList.toggle('is-open', open);
    document.body.classList.toggle('app-menu-open', open);
    setExpanded(button, open);
};

window.addEventListener('DOMContentLoaded', () => {
    document.querySelector('[data-public-menu-toggle]')?.addEventListener('click', () => togglePublicMenu());
    document.querySelector('[data-public-menu-backdrop]')?.addEventListener('click', () => togglePublicMenu(false));
    document.querySelectorAll('#public-mobile-menu a').forEach(a => a.addEventListener('click', () => togglePublicMenu(false)));

    document.querySelector('[data-app-menu-toggle]')?.addEventListener('click', () => toggleAppMenu());
    document.querySelector('[data-app-menu-close]')?.addEventListener('click', () => toggleAppMenu(false));
    document.querySelector('[data-app-menu-backdrop]')?.addEventListener('click', () => toggleAppMenu(false));
    document.querySelectorAll('#app-sidebar a').forEach(a => a.addEventListener('click', () => {
        if (window.matchMedia('(max-width: 720px)').matches) toggleAppMenu(false);
    }));
});

window.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        togglePublicMenu(false);
        toggleAppMenu(false);
    }
});


window.bindMasterDataWorkspace = root => {
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const tabs = [...root.querySelectorAll('[data-master-tab]')];
    const panels = [...root.querySelectorAll('[data-master-panel]')];
    const activate = key => {
        tabs.forEach(tab => tab.classList.toggle('active', tab.dataset.masterTab === key));
        panels.forEach(panel => panel.hidden = panel.dataset.masterPanel !== key);
        try { window.sessionStorage.setItem('sirkel-master-tab', key); } catch {}
        window.setTimeout(() => document.querySelectorAll('select.select').forEach(select => window.refreshSirkelSelect?.(select)), 0);
    };
    tabs.forEach(tab => tab.addEventListener('click', () => activate(tab.dataset.masterTab)));
    let initial = 'groups';
    try { initial = window.sessionStorage.getItem('sirkel-master-tab') || initial; } catch {}
    if (!tabs.some(tab => tab.dataset.masterTab === initial)) initial = 'groups';
    activate(initial);

    root.querySelectorAll('[data-toggle-target]').forEach(button => button.addEventListener('click', () => {
        const target = document.getElementById(button.dataset.toggleTarget);
        if (!target) return;
        target.hidden = !target.hidden;
        if (!target.hidden) target.querySelector('input,select,textarea')?.focus();
    }));
    root.querySelectorAll('[data-close-target]').forEach(button => button.addEventListener('click', () => {
        const target = document.getElementById(button.dataset.closeTarget);
        if (target) target.hidden = true;
    }));

    const search = root.querySelector('[data-master-category-search]');
    const groupFilter = root.querySelector('[data-master-category-group]');
    const cards = [...root.querySelectorAll('[data-master-category-card]')];
    const empty = root.querySelector('[data-master-category-empty]');
    const filterCategories = () => {
        const term = String(search?.value || '').trim().toLocaleLowerCase('id-ID');
        const groupId = String(groupFilter?.value || '');
        let visible = 0;
        cards.forEach(card => {
            const matchesText = !term || String(card.dataset.searchText || '').includes(term);
            const matchesGroup = !groupId || String(card.dataset.groupId || '') === groupId;
            card.hidden = !(matchesText && matchesGroup);
            if (!card.hidden) visible += 1;
        });
        if (empty) empty.hidden = visible > 0;
    };
    search?.addEventListener('input', filterCategories);
    groupFilter?.addEventListener('change', filterCategories);

    root.querySelectorAll('[data-template-scope-select]').forEach(select => {
        const form = select.closest('form');
        const refresh = () => {
            form?.querySelectorAll('[data-template-scope-field]').forEach(field => {
                const active = field.dataset.templateScopeField === select.value;
                field.hidden = !active;
                field.querySelectorAll('select,input').forEach(input => input.disabled = !active);
            });
        };
        select.addEventListener('change', refresh);
        refresh();
    });

    root.querySelectorAll('[data-template-edit-scope]').forEach(form => {
        const group = form.querySelector('select[name="device_group_id"]');
        const category = form.querySelector('select[name="device_category_id"]');
        group?.addEventListener('change', () => {
            if (!group.value || !category) return;
            category.value = '';
            window.refreshSirkelSelect?.(category);
        });
        category?.addEventListener('change', () => {
            if (!category.value || !group) return;
            group.value = '';
            window.refreshSirkelSelect?.(group);
        });
    });

    const optionRow = () => {
        const row = document.createElement('div');
        row.className = 'option-editor-row';
        row.dataset.optionRow = '1';
        const label = document.createElement('input');
        label.className = 'input';
        label.name = 'option_labels[]';
        label.placeholder = 'Tulis pilihan jawaban';
        const value = document.createElement('input');
        value.type = 'hidden'; value.name = 'option_values[]';
        const remove = document.createElement('button');
        remove.type = 'button'; remove.className = 'btn btn-sm'; remove.dataset.removeOption = '1'; remove.textContent = 'Hapus';
        row.append(label, value, remove);
        return row;
    };

    root.querySelectorAll('[data-question-editor]').forEach(form => {
        const type = form.querySelector('[data-question-type]');
        const editor = form.querySelector('[data-option-editor]');
        const list = form.querySelector('[data-option-list]');
        const refresh = () => {
            const needsOptions = ['single','multi'].includes(type?.value || '');
            if (editor) editor.hidden = !needsOptions;
            editor?.querySelectorAll('input,button').forEach(input => input.disabled = !needsOptions);
        };
        type?.addEventListener('change', refresh);
        refresh();
        form.querySelector('[data-add-option]')?.addEventListener('click', () => {
            if (!list) return;
            const row = optionRow();
            list.append(row);
            row.querySelector('input')?.focus();
        });
        form.addEventListener('click', event => {
            const button = event.target.closest('[data-remove-option]');
            if (!button) return;
            const rows = [...(list?.querySelectorAll('[data-option-row]') || [])];
            if (rows.length <= 2 && ['single','multi'].includes(type?.value || '')) {
                rows.find(row => row === button.closest('[data-option-row]'))?.querySelector('input[name="option_labels[]"]')?.setAttribute('value','');
                const input = button.closest('[data-option-row]')?.querySelector('input[name="option_labels[]"]');
                if (input) input.value = '';
                return;
            }
            button.closest('[data-option-row]')?.remove();
        });
    });
};

window.bindRuleBuilder = (form, fields = {}) => {
    if (!form) return;
    const bindPair = (index) => {
        const field = form.querySelector(`[data-rule-field="${index}"]`);
        const value = form.querySelector(`[data-rule-value="${index}"]`);
        if (!field || !value) return;
        const refresh = () => {
            const meta = fields[field.value] || null;
            const options = Array.isArray(meta?.options) ? meta.options : [];
            const optional = index > 1;
            value.innerHTML = optional ? '<option value="">-</option>' : '<option value="">Pilih jawaban</option>';
            options.forEach(option => {
                const el = document.createElement('option');
                el.value = option.value;
                el.textContent = option.label;
                value.appendChild(el);
            });
            const selectedValue = value.dataset.selectedValue || '';
            if (selectedValue && options.some(option => String(option.value) === String(selectedValue))) {
                value.value = selectedValue;
                value.dataset.selectedValue = '';
            }
            value.disabled = !field.value || !options.length;
            if (!field.value && optional) value.disabled = true;
            window.refreshSirkelSelect?.(value);
        };
        field.addEventListener('change', refresh);
        refresh();
    };
    bindPair(1);
    bindPair(2);
    bindPair(3);
};


// v1.0.20 — validasi form harus menunjuk langsung ke bagian yang bermasalah.
function validationFieldName(key) {
    const parts = String(key || '').split('.').filter(Boolean);
    if (!parts.length) return '';
    let name = parts.shift();
    parts.forEach(part => {
        if (/^\d+$/.test(part) || part === '*') return;
        name += `[${part}]`;
    });
    return name;
}

function validationContainerFor(key, field = null) {
    const groupKey = String(key || '').split('.')[0];
    const explicit = document.querySelector(`[data-validation-group="${CSS.escape(groupKey)}"]`)
        || document.querySelector(`[data-validation-field="${CSS.escape(String(key || ''))}"]`);
    if (explicit) return explicit;
    return field?.closest('.field, .assessment-question, .validation-group, .card') || field?.parentElement || null;
}

function clearInlineError(container) {
    if (!container) return;
    container.classList.remove('has-error');
    container.querySelectorAll(':scope > .validation-error, :scope > [data-inline-validation-error]').forEach(el => el.remove());
    container.querySelectorAll('[aria-invalid="true"]').forEach(el => el.removeAttribute('aria-invalid'));
}

function showInlineError(key, message, field = null) {
    const container = validationContainerFor(key, field);
    if (!container) return null;
    clearInlineError(container);
    container.classList.add('has-error');
    const inputs = field ? [field] : [...container.querySelectorAll('input, select, textarea')];
    inputs.forEach(input => input.setAttribute('aria-invalid', 'true'));
    const error = document.createElement('div');
    error.className = 'validation-error';
    error.dataset.inlineValidationError = '1';
    error.textContent = String(message || 'Periksa kembali bagian ini.');
    container.appendChild(error);
    return container;
}

function findFieldForValidationKey(key) {
    const groupKey = String(key || '').split('.')[0];
    const directName = validationFieldName(key);
    const candidates = [
        directName,
        `${directName}[]`,
        groupKey,
        `${groupKey}[]`,
    ].filter(Boolean);
    for (const name of candidates) {
        const field = document.querySelector(`[name="${CSS.escape(name)}"]`);
        if (field) return field;
    }
    return document.querySelector(`[data-validation-group="${CSS.escape(groupKey)}"] input, [data-validation-field="${CSS.escape(String(key || ''))}"] input, [data-validation-field="${CSS.escape(String(key || ''))}"] textarea`);
}

function scrollToValidationTarget(target) {
    if (!target) return;
    window.requestAnimationFrame(() => {
        target.scrollIntoView({behavior: 'smooth', block: 'center'});
        const focusable = target.matches?.('input,select,textarea,button')
            ? target
            : target.querySelector?.('input:not([type="hidden"]), select, textarea, button');
        if (focusable && !focusable.disabled) {
            window.setTimeout(() => focusable.focus({preventScroll: true}), 260);
        }
    });
}

function humanClientValidationMessage(field) {
    const label = field.closest('.field')?.querySelector('label')?.textContent?.replace(/\s*\*\s*$/, '').trim() || 'Bagian ini';
    if (field.validity?.valueMissing) return `${label} wajib diisi.`;
    if (field.validity?.typeMismatch) return `${label} belum menggunakan format yang benar.`;
    if (field.validity?.rangeUnderflow) return `${label} minimal ${field.min}.`;
    if (field.validity?.rangeOverflow) return `${label} maksimal ${field.max}.`;
    if (field.validity?.tooLong) return `${label} terlalu panjang.`;
    if (field.validity?.patternMismatch) return `${label} belum sesuai format yang diminta.`;
    return `Periksa kembali ${label.toLowerCase()}.`;
}

window.addEventListener('DOMContentLoaded', () => {
    // Tampilkan seluruh error backend tepat di field/group masing-masing.
    const errorPayload = document.getElementById('sirkel-validation-errors');
    let firstTarget = null;
    if (errorPayload) {
        try {
            const errors = JSON.parse(errorPayload.textContent || '{}');
            Object.entries(errors).forEach(([key, messages]) => {
                const field = findFieldForValidationKey(key);
                const target = showInlineError(key, Array.isArray(messages) ? messages[0] : messages, field);
                if (!firstTarget && target) firstTarget = target;
            });
        } catch (error) {
            console.warn('Data validasi tidak dapat dibaca.', error);
        }
    }

    // Native HTML validation juga diberi pesan inline, bukan hanya tooltip browser.
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('invalid', event => {
            const field = event.target;
            if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) return;
            const key = field.name || field.id || 'field';
            const target = showInlineError(key, humanClientValidationMessage(field), field);
            if (!firstTarget) firstTarget = target;
            scrollToValidationTarget(target);
        }, true);

        form.querySelectorAll('input,select,textarea').forEach(field => {
            ['input', 'change'].forEach(eventName => field.addEventListener(eventName, () => {
                const container = validationContainerFor(field.name || field.id || 'field', field);
                if (container?.classList.contains('has-error') && field.checkValidity()) clearInlineError(container);
            }));
        });

        // Checkbox group yang diwajibkan Laravel tidak mempunyai native required yang
        // representatif, jadi diperiksa sebagai satu grup sebelum submit.
        form.addEventListener('submit', event => {
            let localFirst = null;
            form.querySelectorAll('[data-required-group]').forEach(group => {
                const checked = group.querySelector('input[type="checkbox"]:checked, input[type="radio"]:checked');
                if (checked) {
                    clearInlineError(group);
                    return;
                }
                event.preventDefault();
                const key = group.dataset.requiredGroup || 'pilihan';
                const target = showInlineError(key, group.dataset.requiredMessage || 'Pilih minimal satu pilihan pada bagian ini.', group.querySelector('input'));
                if (!localFirst) localFirst = target;
            });
            if (localFirst) scrollToValidationTarget(localFirst);
        });
    });

    if (firstTarget) scrollToValidationTarget(firstTarget);
});

// Guard global untuk mencegah double-submit pada aksi mutasi. Backend tetap menjadi
// otoritas utama melalui transaction/row lock; ini hanya melindungi UX dari klik berulang.
window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form').forEach(form => {
        const method = String(form.getAttribute('method') || 'get').toLowerCase();
        if (method === 'get' || form.dataset.allowDoubleSubmit === 'true') return;

        form.addEventListener('submit', event => {
            if (event.defaultPrevented) return;
            if (form.dataset.submitting === '1') {
                event.preventDefault();
                return;
            }
            form.dataset.submitting = '1';
            const submitter = event.submitter;
            if (submitter?.name) {
                const preserved = document.createElement('input');
                preserved.type = 'hidden';
                preserved.name = submitter.name;
                preserved.value = submitter.value;
                form.appendChild(preserved);
            }
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(button => {
                button.disabled = true;
                button.setAttribute('aria-disabled', 'true');
            });
        });
    });
});


window.bindPartnerAssessmentDecisionForm = form => {
    if (!form || form.dataset.decisionBound === '1') return;
    form.dataset.decisionBound = '1';

    const url = form.dataset.decisionOptionsUrl;
    const guidance = form.querySelector('[data-decision-guidance]');
    const choices = [...form.querySelectorAll('[data-decision-option]')];
    const answerFields = [...form.querySelectorAll('[name^="answers["]')];
    if (!url || !answerFields.length || !choices.length) return;

    let requestNumber = 0;
    let timer = null;

    const collectAnswers = () => {
        const result = {};
        answerFields.forEach(field => {
            if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) return;
            const match = field.name.match(/^answers\[([^\]]+)\](\[\])?$/);
            if (!match) return;
            const [, code, isArray] = match;
            if (isArray) {
                if (!Array.isArray(result[code])) result[code] = [];
                if (field.value !== '') result[code].push(field.value);
                return;
            }
            if (field.value !== '') result[code] = field.value;
        });
        return result;
    };

    const setChoiceState = (choice, allowed, message = '') => {
        const input = choice.querySelector('input[name="handling_decision"]');
        const reason = choice.querySelector('[data-decision-reason]');
        const recommendation = choice.querySelector('.badge.warning');
        if (!input) return;

        input.disabled = !allowed;
        choice.classList.toggle('is-disabled', !allowed);
        choice.setAttribute('aria-disabled', allowed ? 'false' : 'true');

        if (!allowed && input.checked) input.checked = false;
        if (recommendation) recommendation.hidden = !allowed;
        if (reason) {
            reason.hidden = allowed || !message;
            reason.textContent = message || '';
        }
    };

    const waitForCondition = () => {
        choices.forEach(choice => setChoiceState(choice, false));
        if (guidance) {
            guidance.classList.remove('success-state', 'warning-state');
            guidance.textContent = 'Isi fungsi utama dan tingkat kerusakan terlebih dahulu. SIRKEL kemudian menyaring langkah berdasarkan pemeriksaan dan kemampuan mitra.';
        }
    };

    const refresh = async () => {
        const answers = collectAnswers();
        if (!answers.power_status || !answers.damage_level) {
            waitForCondition();
            return;
        }

        const currentRequest = ++requestNumber;
        if (guidance) {
            guidance.classList.remove('success-state', 'warning-state');
            guidance.textContent = 'Memeriksa langkah yang sesuai...';
        }

        try {
            const {data} = await axios.post(url, {answers});
            if (currentRequest !== requestNumber) return;

            const availability = data?.availability || {};
            choices.forEach(choice => {
                const code = choice.dataset.decisionCode;
                const state = availability[code];
                setChoiceState(choice, state?.allowed !== false, state?.message || '');
            });

            if (guidance) {
                guidance.textContent = data?.guidance || 'Pilihan langkah sudah disesuaikan dengan hasil pemeriksaan.';
                guidance.classList.add('success-state');
            }
        } catch (error) {
            if (currentRequest !== requestNumber) return;
            // Jangan membuat operator buntu jika helper UI gagal. Backend tetap menjadi guard terakhir.
            choices.forEach(choice => setChoiceState(choice, true));
            if (guidance) {
                guidance.textContent = 'Pilihan langkah belum dapat diperbarui. Periksa kembali jawaban Anda lalu coba lagi.';
                guidance.classList.add('warning-state');
            }
        }
    };

    const scheduleRefresh = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(refresh, 120);
    };

    answerFields.forEach(field => {
        field.addEventListener('change', scheduleRefresh);
        if (field.tagName === 'TEXTAREA' || field.type === 'text') field.addEventListener('input', scheduleRefresh);
    });

    const initialAnswers = collectAnswers();
    if (!initialAnswers.power_status || !initialAnswers.damage_level) waitForCondition();
    else refresh();
};

window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-partner-assessment-form]').forEach(form => window.bindPartnerAssessmentDecisionForm(form));
});

/* v1.0.44 — unlimited cart, resumable standard intake, Bulk AI questionnaire */
function bindSirkelCartSelection(form) {
    if (!form || form.dataset.cartBound === '1') return;
    form.dataset.cartBound = '1';
    const boxes = [...form.querySelectorAll('[data-cart-item-checkbox]')];
    const count = form.querySelector('[data-cart-selected-count]');
    const button = form.querySelector('[data-cart-process-button]');
    const refresh = () => {
        const selected = boxes.filter(box => box.checked);
        if (count) count.textContent = String(selected.length);
        if (button) button.disabled = selected.length < 1 || selected.length > 3;
        boxes.forEach(box => {
            box.disabled = !box.checked && selected.length >= 3;
            box.closest('.cart-item-card')?.classList.toggle('selected', box.checked);
        });
    };
    boxes.forEach(box => box.addEventListener('change', refresh));
    refresh();
}

function bindIntakeAutosave(form) {
    if (!form || form.dataset.autosaveBound === '1' || !form.dataset.autosaveUrl) return;
    form.dataset.autosaveBound = '1';
    const status = form.querySelector('[data-autosave-state]');
    let timer = null;
    let requestId = 0;
    const setStatus = (text, tone = '') => {
        if (!status) return;
        status.textContent = text;
        status.dataset.tone = tone;
    };
    const save = async () => {
        const current = ++requestId;
        setStatus('Menyimpan…');
        try {
            await axios.post(form.dataset.autosaveUrl, new FormData(form));
            if (current !== requestId) return;
            setStatus('Tersimpan', 'success');
        } catch (error) {
            if (current !== requestId) return;
            setStatus('Belum tersimpan — gunakan “Simpan & Keluar”', 'warning');
        }
    };
    const schedule = () => {
        window.clearTimeout(timer);
        setStatus('Ada perubahan…');
        timer = window.setTimeout(save, 550);
    };
    form.querySelectorAll('input[name^="answers["], textarea[name^="answers["], select[name^="answers["]').forEach(field => {
        field.addEventListener('change', schedule);
        if (field.tagName === 'TEXTAREA' || field.type === 'text') field.addEventListener('input', schedule);
    });
}

function bindBulkQuestionnaire(form) {
    if (!form || form.dataset.bulkBound === '1') return;
    form.dataset.bulkBound = '1';
    const questions = [...form.querySelectorAll('[data-bulk-question]')];
    if (!questions.length) return;
    const prev = form.querySelector('[data-bulk-prev]');
    const next = form.querySelector('[data-bulk-next]');
    const finish = form.querySelector('[data-bulk-finish]');
    const progress = form.querySelector('[data-bulk-question-progress]');
    const bar = form.querySelector('[data-bulk-progress-bar]');
    const saveState = form.querySelector('[data-bulk-autosave-state]');
    let current = 0;
    let timer = null;
    let saveRequest = 0;

    const show = index => {
        current = Math.max(0, Math.min(questions.length - 1, index));
        questions.forEach((question, i) => { question.hidden = i !== current; });
        if (prev) prev.disabled = current === 0;
        if (next) next.hidden = current >= questions.length - 1;
        if (finish) finish.hidden = current < questions.length - 1;
        if (progress) progress.textContent = `${current + 1} dari ${questions.length}`;
        if (bar) bar.style.width = `${((current + 1) / questions.length) * 100}%`;
        questions[current]?.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    };

    const autosave = async () => {
        if (!form.dataset.autosaveUrl) return;
        const id = ++saveRequest;
        if (saveState) saveState.textContent = 'Menyimpan jawaban…';
        try {
            await axios.post(form.dataset.autosaveUrl, new FormData(form));
            if (id !== saveRequest) return;
            if (saveState) saveState.textContent = 'Jawaban tersimpan';
        } catch (error) {
            if (id !== saveRequest) return;
            if (saveState) saveState.textContent = 'Autosave belum berhasil';
        }
    };
    const scheduleSave = () => {
        window.clearTimeout(timer);
        if (saveState) saveState.textContent = 'Ada perubahan…';
        timer = window.setTimeout(autosave, 550);
    };

    const questionComplete = question => {
        if (!question || question.dataset.questionRequired !== '1') return true;
        const type = question.dataset.questionType || 'single';
        if (type === 'text') return Boolean(question.querySelector('textarea')?.value?.trim());
        if (type.startsWith('matrix_')) {
            const rows = [...question.querySelectorAll('.bulk-matrix-row')];
            return rows.length > 0 && rows.every(row => Boolean(row.querySelector('input:checked')));
        }
        return Boolean(question.querySelector('input:checked'));
    };

    form.querySelectorAll('input[name^="answers["], textarea[name^="answers["]').forEach(field => {
        field.addEventListener('change', () => {
            // Pada pilihan multi, “tidak ada” dan “tidak tahu” bersifat eksklusif
            // agar payload tidak baru ditolak ketika user menekan tombol selesai.
            if (field.type === 'checkbox' && field.checked) {
                const name = field.getAttribute('name');
                const peers = name ? [...form.querySelectorAll(`input[type="checkbox"][name="${CSS.escape(name)}"]`)] : [];
                const exclusive = ['none', 'unknown'];
                if (exclusive.includes(field.value)) {
                    peers.forEach(peer => { if (peer !== field) peer.checked = false; });
                } else {
                    peers.forEach(peer => { if (exclusive.includes(peer.value)) peer.checked = false; });
                }
            }
            scheduleSave();
        });
        if (field.tagName === 'TEXTAREA') field.addEventListener('input', scheduleSave);
    });
    prev?.addEventListener('click', () => show(current - 1));
    next?.addEventListener('click', () => {
        if (!questionComplete(questions[current])) {
            if (saveState) saveState.textContent = 'Jawab pertanyaan ini sebelum melanjutkan.';
            questions[current]?.querySelector('input, textarea')?.focus({preventScroll: true});
            return;
        }
        scheduleSave();
        show(current + 1);
    });
    show(0);
}

window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-cart-process-form]').forEach(bindSirkelCartSelection);
    document.querySelectorAll('[data-intake-autosave-form]').forEach(bindIntakeAutosave);
    document.querySelectorAll('[data-bulk-questionnaire-form]').forEach(bindBulkQuestionnaire);
});

function bindCameraFilePicker(picker) {
    if (!picker || picker.dataset.cameraPickerBound === '1') return;
    picker.dataset.cameraPickerBound = '1';
    const main = picker.querySelector('[data-camera-main-input]');
    const capture = picker.querySelector('[data-camera-capture-input]');
    const galleryButton = picker.querySelector('[data-camera-gallery]');
    const cameraButton = picker.querySelector('[data-camera-capture]');
    if (!main || !capture) return;

    galleryButton?.addEventListener('click', () => main.click());
    cameraButton?.addEventListener('click', () => capture.click());
    capture.addEventListener('change', () => {
        const incoming = Array.from(capture.files || []);
        if (!incoming.length) return;
        const maxFiles = Math.max(1, Number(picker.dataset.maxFiles || (main.multiple ? 99 : 1)));
        const selected = main.multiple
            ? [...Array.from(main.files || []), ...incoming].slice(0, maxFiles)
            : incoming.slice(-1);
        try {
            const dt = new DataTransfer();
            selected.forEach(file => dt.items.add(file));
            main.files = dt.files;
        } catch (error) {
            // Fallback browser lama: setidaknya gunakan hasil kamera terbaru.
            try { main.files = capture.files; } catch (_) {}
        }
        main.dispatchEvent(new Event('change', {bubbles: true}));
        capture.value = '';
    });
}

window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-camera-file-picker]').forEach(bindCameraFilePicker);
});
