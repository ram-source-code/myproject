/* Aqarat — progressive enhancement. Core browsing works without JS. */
(() => {
    'use strict';

    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => [...r.querySelectorAll(s)];
    const meta = (n) => document.querySelector(`meta[name="${n}"]`)?.content || '';
    const BASE = meta('app-base');
    const CSRF = meta('csrf-token');
    const RTL = document.documentElement.dir === 'rtl';
    let i18n = {};
    try { i18n = JSON.parse($('#i18n')?.textContent || '{}'); } catch { /* ignore */ }
    const tr = (k, vars = {}) => (i18n[k] || k).replace(/:(\w+)/g, (_, n) => vars[n] ?? '');
    const store = {
        get(k, d) { try { return JSON.parse(localStorage.getItem(k)) ?? d; } catch { return d; } },
        set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch { /* private mode */ } },
        del(k) { try { localStorage.removeItem(k); } catch { /* ignore */ } },
    };
    const el = (tag, attrs = {}, ...kids) => {
        const n = document.createElement(tag);
        for (const [k, v] of Object.entries(attrs)) {
            if (v === null || v === undefined || v === false) continue;
            if (k === 'class') n.className = v; else if (k === 'text') n.textContent = v; else n.setAttribute(k, v === true ? '' : v);
        }
        n.append(...kids.filter((x) => x !== null && x !== undefined));
        return n;
    };
    const icon = (id) => {
        const s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        s.setAttribute('aria-hidden', 'true');
        const u = document.createElementNS('http://www.w3.org/2000/svg', 'use');
        u.setAttribute('href', '#i-' + id);
        s.append(u);
        return s;
    };
    async function postJSON(path, body) {
        const res = await fetch(BASE + path, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify(body),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.ok === false) throw Object.assign(new Error(data.error || 'request'), { data });
        return data;
    }

    function toast(msg) {
        if (!msg) return;
        $$('.toast').forEach((t) => t.remove());
        const t = el('div', { class: 'toast', role: 'status', text: msg });
        document.body.append(t);
        setTimeout(() => t.remove(), 3200);
    }

    /* ---------- Dominant-colour placeholders (computed at upload) ---------- */
    const paintPlaceholders = (root = document) => $$('[data-color]', root).forEach((n) => { if (/^#[0-9a-f]{6}$/.test(n.dataset.color)) n.style.backgroundColor = n.dataset.color; });
    paintPlaceholders();

    /* ---------- View transitions: morph the card photo into the listing hero (and back) ---------- */
    const pathOf = (u) => { try { return decodeURIComponent(new URL(u, location.href).pathname); } catch { return ''; } };
    const cardImgFor = (path) => $$('.property-card a.stretched-link').find((a) => pathOf(a.href) === path)?.closest('.card')?.querySelector('.card-photo');
    const touch = matchMedia('(pointer: coarse)').matches;
    window.addEventListener('pageswap', (e) => {
        if (e.viewTransition && touch && e.activation?.navigationType === 'traverse') { e.viewTransition.skipTransition(); return; }
        if (!e.viewTransition || !e.activation?.entry?.url) return;
        const img = cardImgFor(pathOf(e.activation.entry.url));
        if (img && !$('.hero-photo')) img.classList.add('vt-hero');
    });
    window.addEventListener('pagereveal', (e) => {
        if (e.viewTransition && touch && window.navigation?.activation?.navigationType === 'traverse') { e.viewTransition.skipTransition(); return; }
        if (!e.viewTransition || !window.navigation?.activation?.from?.url) return;
        const img = $('.hero-photo') ? null : cardImgFor(pathOf(navigation.activation.from.url));
        if (img) {
            img.classList.add('vt-hero');
            e.viewTransition.finished.finally(() => img.classList.remove('vt-hero'));
        }
    });
    window.addEventListener('pageshow', () => $$('.vt-hero').forEach((n) => n.classList.remove('vt-hero')));

    /* ---------- Count a listing view only when a prerendered page is actually shown ---------- */
    const beacon = $('[data-view-beacon]');
    if (beacon && document.prerendering) {
        document.addEventListener('prerenderingchange', () => {
            const fd = new FormData();
            fd.append('_csrf', CSRF);
            navigator.sendBeacon?.(beacon.dataset.viewBeacon, fd);
        }, { once: true });
    }

    /* ---------- Count WhatsApp / Call taps for the office's stats (beacon: never delays the tap) ---------- */
    document.addEventListener('click', (e) => {
        const a = e.target.closest?.('[data-track-contact]');
        if (!a || !navigator.sendBeacon) return;
        const fd = new FormData();
        fd.append('_csrf', CSRF);
        fd.append('kind', a.dataset.trackContact);
        navigator.sendBeacon(a.dataset.trackUrl, fd);
    });

    /* ---------- Service worker ---------- */
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register(BASE + '/sw.js', { scope: BASE + '/' }).catch(() => {});
        });
    }
    $$('form[data-logout]').forEach((f) => f.addEventListener('submit', () => {
        navigator.serviceWorker?.controller?.postMessage('clear-pages');
    }));
    window.addEventListener('offline', () => toast(tr('offline_now')));
    window.addEventListener('online', () => toast(tr('online_now')));
    $$('[data-reload]').forEach((a) => a.addEventListener('click', (e) => { e.preventDefault(); location.reload(); }));

    /* ---------- Install (PWA) ---------- */
    const isStandalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const banner = $('[data-install-banner]');
    let deferredPrompt = null;
    const bannerDismissed = () => store.get('installDismissed', 0) > Date.now() - 14 * 864e5;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        $$('[data-install]').forEach((b) => { b.hidden = false; });
        if (banner && !bannerDismissed()) banner.hidden = false;
    });
    const iosSheet = $('#ios-install');
    const showIosGuide = () => { if (iosSheet && !iosSheet.open) iosSheet.showModal(); };
    if (isIOS && !isStandalone && banner && !bannerDismissed()) {
        $$('[data-install]').forEach((b) => { b.hidden = false; });
        banner.hidden = false;
    }
    $$('[data-install]').forEach((b) => b.addEventListener('click', async () => {
        if (isIOS && !isStandalone) { showIosGuide(); return; }
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        await deferredPrompt.userChoice.catch(() => {});
        deferredPrompt = null;
        if (banner) banner.hidden = true;
    }));
    $('[data-install-dismiss]')?.addEventListener('click', () => { store.set('installDismissed', Date.now()); banner.hidden = true; });
    window.addEventListener('appinstalled', () => { if (banner) banner.hidden = true; });

    /* ---------- Installed app: back button (iOS/Android standalone have no browser chrome) ---------- */
    const backBtn = $('[data-app-back]');
    if (backBtn && isStandalone) {
        const atHome = location.pathname.replace(/\/$/, '') === BASE;
        backBtn.hidden = atHome;
        backBtn.addEventListener('click', () => {
            const fromHere = document.referrer && new URL(document.referrer).origin === location.origin;
            if (fromHere && history.length > 1) history.back(); else location.href = BASE + '/';
        });
    }

    /* ---------- On-screen keyboard: hide fixed bottom bars while typing (iOS ignores interactive-widget) ---------- */
    if (window.visualViewport && matchMedia('(pointer: coarse)').matches) {
        const vv = window.visualViewport;
        const check = () => {
            const typing = document.activeElement?.matches?.('input:not([type=checkbox]):not([type=radio]), textarea, select');
            document.documentElement.classList.toggle('kb-open', Boolean(typing) && window.innerHeight - vv.height > 150);
        };
        vv.addEventListener('resize', check);
        document.addEventListener('focusout', () => setTimeout(check, 50));
    }

    /* ---------- Header menus (<details>) ---------- */
    const menus = $$('details.menu');
    document.addEventListener('click', (e) => menus.forEach((m) => { if (m.open && !m.contains(e.target)) m.open = false; }));
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        menus.forEach((m) => { if (m.open) { m.open = false; $('summary', m).focus(); } });
    });

    /* ---------- Dismissible alerts, autosubmit, error focus ---------- */
    document.addEventListener('click', (e) => { e.target.closest('[data-dismiss]')?.closest('.alert')?.remove(); });
    $('[data-focus]')?.focus();
    $$('form[data-autosubmit] select').forEach((s) => s.addEventListener('change', () => s.form.requestSubmit()));
    const filters = $('.filters-panel');
    if (filters && matchMedia('(max-width: 960px)').matches) filters.open = false;

    /* ---------- Confirm dialog for destructive forms ---------- */
    const confirmDlg = $('#confirm-dialog');
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed) return;
        if (!confirmDlg || typeof confirmDlg.showModal !== 'function') {
            if (!window.confirm(form.dataset.confirm)) e.preventDefault();
            return;
        }
        e.preventDefault();
        $('#confirm-text', confirmDlg).textContent = form.dataset.confirm;
        confirmDlg.returnValue = '';
        confirmDlg.showModal();
        confirmDlg.addEventListener('close', () => {
            if (confirmDlg.returnValue === 'confirm') { form.dataset.confirmed = '1'; form.requestSubmit(); }
            confirmDlg.returnValue = '';
        }, { once: true });
    }, true);
    if (!('closedBy' in HTMLDialogElement.prototype)) {
        $$('dialog[closedby="any"]').forEach((d) => d.addEventListener('click', (e) => {
            if (e.target !== d) return;
            const r = d.getBoundingClientRect();
            const inside = e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom;
            if (!inside || d.classList.contains('lightbox')) d.close();
        }));
    }

    /* ---------- Favourites without reload ---------- */
    document.addEventListener('submit', async (e) => {
        const form = e.target;
        if (!form.matches?.('.fav-form, .fav-form-inline')) return;
        e.preventDefault();
        const btn = $('button', form);
        btn.disabled = true;
        try {
            const res = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json' }, body: new FormData(form), credentials: 'same-origin' });
            const data = await res.json().catch(() => ({}));
            if (res.status === 401 && data.login) { location.href = data.login; return; }
            if (!res.ok || !data.ok) { form.submit(); return; }
            btn.setAttribute('aria-pressed', String(data.saved));
            const lbl = $('[data-fav-label]', btn);
            if (lbl) lbl.textContent = data.label;
            else {
                const title = btn.getAttribute('aria-label').split(': ').slice(1).join(': ');
                btn.setAttribute('aria-label', data.label + (title ? ': ' + title : ''));
            }
            toast(data.label);
        } catch { form.submit(); } finally { btn.disabled = false; }
    });

    /* ---------- Share ---------- */
    $$('[data-share]').forEach((b) => b.addEventListener('click', async () => {
        try {
            if (navigator.share) await navigator.share({ title: b.dataset.title, url: location.href });
            else { await navigator.clipboard.writeText(location.href); toast(tr('link_copied')); }
        } catch { /* cancelled */ }
    }));

    /* ---------- QR code for the authenticator app (drawn as SVG paths: no inline styles, CSP-safe) ---------- */
    const qrBox = $('[data-qr]');
    if (qrBox && typeof window.qrcode === 'function') {
        const qr = window.qrcode(0, 'M');
        qr.addData(qrBox.dataset.qr);
        qr.make();
        const n = qr.getModuleCount(), m = 4;
        let d = '';
        for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) if (qr.isDark(r, c)) d += `M${c + m} ${r + m}h1v1h-1z`;
        const NS = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(NS, 'svg');
        svg.setAttribute('viewBox', `0 0 ${n + 2 * m} ${n + 2 * m}`);
        svg.setAttribute('class', 'qr-svg');
        svg.setAttribute('aria-hidden', 'true');
        const bg = document.createElementNS(NS, 'rect');
        bg.setAttribute('width', '100%'); bg.setAttribute('height', '100%'); bg.setAttribute('class', 'qr-bg');
        const path = document.createElementNS(NS, 'path');
        path.setAttribute('d', d); path.setAttribute('class', 'qr-fg');
        svg.append(bg, path);
        qrBox.replaceChildren(svg);
    }

    /* ---------- Neighbourhoods follow the chosen city (filters: a select; listing form: suggestions) ---------- */
    $$('[data-districts]').forEach((target) => {
        let map = {};
        try { map = JSON.parse(target.dataset.districts); } catch { return; }
        const city = document.getElementById(target.dataset.citySelect);
        if (!city) return;
        const fill = () => {
            const list = map[city.value] || [];
            if (target.tagName === 'SELECT') {
                const keep = target.value;
                target.replaceChildren(target.options[0], ...list.map(([id, name]) => el('option', { value: id, selected: String(id) === keep }, name)));
                const field = target.closest('[data-district-field]');
                if (field) field.hidden = !list.length;
                if (!list.some(([id]) => String(id) === keep)) target.value = '';
            } else {
                const dl = document.getElementById(target.getAttribute('list'));
                if (dl) dl.replaceChildren(...list.map(([, name]) => el('option', { value: name })));
            }
        };
        city.addEventListener('change', fill);
        if (target.tagName !== 'SELECT') fill();
    });

    /* ---------- Buttons that need a quick "are you sure?" ---------- */
    document.addEventListener('click', (e) => {
        const b = e.target.closest?.('[data-confirm-click]');
        if (b && !window.confirm(b.dataset.confirmClick)) e.preventDefault();
    });

    /* ---------- Copy to clipboard (activation codes) ---------- */
    $$('[data-copy]').forEach((b) => b.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(b.dataset.copy); toast(tr('copied')); }
        catch { window.prompt('', b.dataset.copy); }      // older browsers / insecure origin: select-and-copy fallback
    }));
    $('[data-autofocus]')?.focus();

    /* ---------- Password show/hide ---------- */
    $$('[data-pw-toggle]').forEach((b) => b.addEventListener('click', () => {
        const input = document.getElementById(b.getAttribute('aria-controls'));
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        b.setAttribute('aria-pressed', String(show));
        b.textContent = show ? tr('hide') : tr('show');
    }));

    /* ---------- Accessible validation: aria-invalid mirrors :user-invalid ---------- */
    const supportsUserInvalid = CSS.supports('selector(:user-invalid)');
    function syncValidity(input) {
        if (input.dataset.match) {
            const other = document.getElementById(input.dataset.match);
            input.setCustomValidity(other && input.value && other.value !== input.value ? tr('v_password_match') : '');
        }
        const bad = supportsUserInvalid ? input.matches(':user-invalid') : (input.dataset.touched && !input.checkValidity());
        if (bad) input.setAttribute('aria-invalid', 'true');
        else if (!input.closest('.field')?.querySelector('.field-error')) input.removeAttribute('aria-invalid');
    }
    $$('form[data-validate]').forEach((form) => {
        $$('input, select, textarea', form).forEach((c) => {
            c.addEventListener('blur', () => { c.dataset.touched = '1'; syncValidity(c); });
            c.addEventListener('input', () => syncValidity(c));
        });
        form.addEventListener('submit', (e) => {
            $$('[data-match]', form).forEach(syncValidity);
            if (form.checkValidity()) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            const ctrls = $$('input, select, textarea', form);
            ctrls.forEach((c) => { c.dataset.touched = '1'; if (!c.checkValidity()) c.setAttribute('aria-invalid', 'true'); });
            const first = ctrls.find((c) => !c.checkValidity());
            first?.focus();
            first?.reportValidity();
        });
    });

    /* ---------- Conditional fields ---------- */
    const bindToggle = (radios, target, isOn) => {
        if (!radios.length || !target) return;
        const upd = () => {
            const on = isOn();
            target.hidden = !on;
            $$('input', target).forEach((i) => { i.required = on && i.name === 'office_name'; });
        };
        radios.forEach((r) => r.addEventListener('change', upd));
        upd();
    };
    bindToggle($$('[data-purpose]'), $('[data-rent-only]'), () => $('[data-purpose]:checked')?.value === 'rent');
    bindToggle($$('[data-role]'), $('[data-office-only]'), () => $('[data-role]:checked')?.value === 'agent');
    $$('[data-counter]').forEach((ta) => {
        const out = $(`[data-count-for="${ta.id}"]`);
        const upd = () => { if (out) out.textContent = ta.value.length; };
        ta.addEventListener('input', upd);
        upd();
    });

    /* ---------- Notification compose preview ---------- */
    $$('form[data-push-preview]').forEach((f) => {
        const t = $('[name=title]', f), b = $('[name=body]', f);
        const pt = $('[data-preview-title]', f), pb = $('[data-preview-body]', f);
        const prefix = pt.textContent.replace(/…$/, '');
        const upd = () => { pt.textContent = prefix + (t.value || '…'); pb.textContent = b.value || '…'; };
        t.addEventListener('input', upd); b.addEventListener('input', upd); upd();
    });

    /* ---------- Image upload: gallery + camera + drag & drop, one combined list with previews ---------- */
    const dz = $('[data-dropzone]');
    if (dz) {
        const input = $('input[type=file]', dz);
        const camera = $('[data-camera]');
        const preview = $('[data-preview]');
        const status = $('#upload-status');
        const maxBytes = Number(input.dataset.maxBytes);
        const maxFiles = Number(input.dataset.maxFiles);
        const canMerge = typeof DataTransfer === 'function' && (() => { try { return Boolean(new DataTransfer().files); } catch { return false; } })();
        const isHeic = (f) => /^image\/hei[cf]/.test(f.type) || /\.hei[cf]$/i.test(f.name);
        let picked = [];
        let urls = [];
        const key = (f) => `${f.name}|${f.size}|${f.lastModified}`;
        const sync = () => {
            if (!canMerge) return;
            const dt = new DataTransfer();
            picked.forEach((f) => dt.items.add(f));
            input.files = dt.files;
        };
        const render = () => {
            urls.forEach(URL.revokeObjectURL);
            urls = [];
            preview.replaceChildren();
            const files = canMerge ? picked : [...input.files];
            const msgs = [tr('files_selected', { n: files.length })];
            if (files.length > maxFiles) msgs.push(tr('too_many_files', { n: maxFiles }));
            files.forEach((f, i) => {
                const u = URL.createObjectURL(f);
                urls.push(u);
                const li = el('li', {}, el('img', { src: u, alt: f.name }));
                if (f.size > maxBytes) { li.classList.add('is-bad'); msgs.push(tr('file_too_big', { name: f.name })); }
                if (isHeic(f)) { li.classList.add('is-bad'); msgs.push(tr('img_heic_client', { name: f.name })); }
                if (canMerge) {
                    const rm = el('button', { type: 'button', class: 'preview-remove', 'aria-label': `${tr('remove_photo')} ${i + 1}` }, '×');
                    rm.addEventListener('click', () => { picked.splice(i, 1); sync(); render(); });
                    li.append(rm);
                }
                preview.append(li);
            });
            status.textContent = files.length ? msgs.join(' · ') : '';
        };
        // Adds instead of replacing, so several camera shots and gallery picks end up in one listing.
        const add = (files) => {
            const have = new Set(picked.map(key));
            [...files].filter((f) => f.type.startsWith('image/') && !have.has(key(f))).forEach((f) => picked.push(f));
            sync();
            render();
        };
        input.addEventListener('change', () => (canMerge ? add(input.files) : render()));
        if (camera) {
            if (canMerge) {
                camera.addEventListener('change', () => { add(camera.files); camera.value = ''; });
            } else {
                camera.name = 'images[]';            // very old browsers: send the shot as its own field
            }
        }
        ['dragenter', 'dragover'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.add('is-dragover'); }));
        ['dragleave', 'drop'].forEach((ev) => dz.addEventListener(ev, () => dz.classList.remove('is-dragover')));
        dz.addEventListener('drop', (e) => {
            e.preventDefault();
            if (canMerge) add(e.dataTransfer.files);
        });

        /* Publish: shrink big photos on the phone, then upload with a progress bar. */
        const form = dz.closest('form[data-upload-form]');
        const prog = form && $('[data-upload-progress]', form);
        const MAX_SIDE = 2400;
        const fileLimit = Number(form?.dataset.fileLimit) || maxBytes;
        const postLimit = Number(form?.dataset.postLimit) || 0;   // 0 = server has no total limit
        // Re-encode until the photo fits its share of what this server accepts: lower quality first, then size.
        const STEPS = [[2400, 0.86], [2400, 0.78], [2000, 0.74], [1600, 0.7], [1280, 0.66]];
        const shrink = async (f, budget) => {
            if (!/^image\/(jpeg|png|webp)$/.test(f.type) || isHeic(f)) return f;
            const url = URL.createObjectURL(f);
            try {
                const img = new Image();
                img.src = url;
                await img.decode();                       // browsers apply the photo's EXIF rotation here
                const w = img.naturalWidth, h = img.naturalHeight;
                if (Math.max(w, h) <= MAX_SIDE && f.size <= Math.min(budget, 1.5 * 1048576)) return f;
                let best = f;
                for (const [side, q] of STEPS) {
                    const k = Math.min(1, side / Math.max(w, h));
                    const c = document.createElement('canvas');
                    c.width = Math.round(w * k);
                    c.height = Math.round(h * k);
                    const ctx = c.getContext('2d');
                    ctx.fillStyle = '#fff';               // transparent PNG → white, not black
                    ctx.fillRect(0, 0, c.width, c.height);
                    ctx.drawImage(img, 0, 0, c.width, c.height);
                    const blob = await new Promise((res) => c.toBlob(res, 'image/jpeg', q));
                    if (blob && blob.size < best.size) best = new File([blob], f.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: f.lastModified });
                    if (best.size <= budget) break;
                }
                return best;
            } catch {
                return f;                                 // can't decode here: let the server decide
            } finally {
                URL.revokeObjectURL(url);
            }
        };
        if (form && prog && canMerge && 'FormData' in window) {
            const bar = $('progress', prog);
            const text = $('[data-upload-text]', prog);
            const btn = $('.form-footer button[type=submit]', form);
            let busy = false;
            form.addEventListener('submit', async (e) => {
                if (e.defaultPrevented) return;           // validation already stopped it
                e.preventDefault();
                if (busy) return;
                busy = true;
                btn.disabled = true;
                prog.hidden = false;
                bar.removeAttribute('value');             // indeterminate while preparing
                const fd = new FormData(form);
                fd.delete('images[]');
                // Each photo's budget: the per-file limit, and an equal share of the total request limit
                // (after videos and the text fields), so the whole form always fits.
                let others = 64 * 1024;
                for (const [, v] of fd.entries()) if (v instanceof File) others += v.size;
                const share = postLimit && picked.length ? Math.floor((postLimit * 0.95 - others) / picked.length) : Infinity;
                const budget = Math.max(150 * 1024, Math.min(fileLimit * 0.97, share));
                for (let i = 0; i < picked.length; i++) {
                    text.textContent = tr('preparing_photos', { n: i + 1, total: picked.length });
                    fd.append('images[]', await shrink(picked[i], budget));
                }
                const xhr = new XMLHttpRequest();
                xhr.open('POST', form.action);
                xhr.setRequestHeader('X-AQ-Upload', '1');
                xhr.upload.onprogress = (ev) => {
                    if (!ev.lengthComputable) return;
                    const pct = Math.round((ev.loaded / ev.total) * 100);
                    bar.value = pct;
                    text.textContent = pct < 100 ? tr('uploading_pct', { pct }) : tr('saving');
                };
                const fail = () => {
                    busy = false;
                    btn.disabled = false;
                    bar.value = 0;
                    text.textContent = tr('upload_failed');
                };
                xhr.onerror = fail;
                xhr.ontimeout = fail;
                xhr.onload = () => {
                    let data = null;
                    try { data = JSON.parse(xhr.responseText); } catch { /* an error page */ }
                    if (data && data.redirect) { location.href = data.redirect; return; }
                    if (xhr.status >= 200 && xhr.status < 600 && xhr.responseText) {
                        document.open(); document.write(xhr.responseText); document.close();   // show the server's page
                        return;
                    }
                    fail();
                };
                xhr.send(fd);
            });
        }
    }

    /* ---------- Video: recorded clips join the chosen ones ---------- */
    const vidInput = $('[data-video-input]');
    const vidCam = $('[data-video-camera]');
    if (vidInput && vidCam) {
        if (typeof DataTransfer === 'function') {
            vidCam.addEventListener('change', () => {
                const dt = new DataTransfer();
                [...vidInput.files, ...vidCam.files].forEach((f) => dt.items.add(f));
                vidInput.files = dt.files;
                vidCam.value = '';
                vidInput.dispatchEvent(new Event('change'));
            });
        } else {
            vidCam.name = 'videos[]';
        }
    }

    /* ---------- Mobile photo strip: position counter ---------- */
    const strip = $('[data-gallery]');
    const pos = $('[data-gallery-pos]');
    if (strip && pos && 'IntersectionObserver' in window) {
        const items = $$('.gallery-item', strip);
        const io = new IntersectionObserver((entries) => entries.forEach((en) => {
            if (en.isIntersecting) pos.textContent = String(items.indexOf(en.target) + 1);
        }), { root: strip, threshold: 0.6 });
        items.forEach((i) => io.observe(i));
    }

    /* ---------- Filters: bottom sheet on phones + live "Show N results" ---------- */
    const filterForm = $('.filters-form');
    const sheet = $('#filter-sheet');
    if (filterForm && sheet) {
        const panel = $('.filters-panel');
        const body = $('[data-sheet-body]', sheet);
        const mq = matchMedia('(max-width: 960px)');
        const place = () => {
            if (mq.matches && filterForm.parentElement !== body) body.append(filterForm);
            if (!mq.matches && filterForm.parentElement === body) { if (sheet.open) sheet.close(); panel.append(filterForm); }
        };
        place();
        mq.addEventListener('change', place);
        $$('[data-open-filters]').forEach((b) => b.addEventListener('click', () => sheet.showModal()));
    }
    const liveBtn = $('[data-live-count]');
    if (filterForm && liveBtn) {
        let timer = 0, ctrl = null;
        const update = () => {
            clearTimeout(timer);
            timer = setTimeout(async () => {
                ctrl?.abort();
                ctrl = new AbortController();
                const params = new URLSearchParams(new FormData(filterForm));
                for (const [k, v] of [...params]) if (v === '') params.delete(k);
                liveBtn.classList.add('is-counting');
                try {
                    const r = await fetch(liveBtn.dataset.endpoint + '?' + params, { signal: ctrl.signal, headers: { Accept: 'application/json' } });
                    const d = await r.json();
                    if (d.ok) liveBtn.textContent = d.label;
                } catch { /* aborted or offline */ } finally { liveBtn.classList.remove('is-counting'); }
            }, 250);
        };
        filterForm.addEventListener('input', update);
        filterForm.addEventListener('change', update);
    }

    /* ---------- Installer: busy state while demo data is generated ---------- */
    $$('form[data-install-form]').forEach((f) => f.addEventListener('submit', (e) => {
        if (e.defaultPrevented) return;
        const b = $('button[type=submit]', f);
        if (b) { b.disabled = true; b.lastChild.textContent = b.dataset.busyLabel || b.textContent; }
    }));

    /* ---------- Gallery lightbox ---------- */
    const gallery = $('[data-gallery]');
    const lb = $('#lightbox');
    if (gallery && lb) {
        const thumbs = $$('[data-lb-go]', lb);
        const img = $('[data-lb-img]', lb);
        const cur = $('[data-lb-current]', lb);
        let idx = 0;
        const show = (i) => {
            idx = (i + thumbs.length) % thumbs.length;
            img.src = thumbs[idx].dataset.full;
            img.alt = thumbs[idx].getAttribute('aria-label');
            cur.textContent = String(idx + 1);
            thumbs.forEach((t, n) => t.setAttribute('aria-current', String(n === idx)));
            thumbs[idx].scrollIntoView({ block: 'nearest', inline: 'center' });
            [idx + 1, idx - 1].forEach((n) => { const t = thumbs[(n + thumbs.length) % thumbs.length]; if (t) new Image().src = t.dataset.full; });
        };
        gallery.addEventListener('click', (e) => {
            const b = e.target.closest('[data-index]');
            if (!b) return;
            lb.showModal();
            show(Number(b.dataset.index));
        });
        $('[data-lb-prev]', lb).addEventListener('click', () => show(idx - 1));
        $('[data-lb-next]', lb).addEventListener('click', () => show(idx + 1));
        thumbs.forEach((t, n) => t.addEventListener('click', () => show(n)));
        lb.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') { e.preventDefault(); show(idx + (RTL ? 1 : -1)); }
            if (e.key === 'ArrowRight') { e.preventDefault(); show(idx + (RTL ? -1 : 1)); }
        });
        let x0 = null;
        lb.addEventListener('pointerdown', (e) => { if (e.target === img) x0 = e.clientX; });
        lb.addEventListener('pointerup', (e) => {
            if (x0 === null) return;
            const dx = e.clientX - x0;
            x0 = null;
            if (Math.abs(dx) > 50) show(idx + ((dx < 0) !== RTL ? 1 : -1));
        });
    }

    /* ---------- Recently viewed (stored on this device only) ---------- */
    const recentItem = $('[data-recent-item]');
    if (recentItem) {
        const d = recentItem.dataset;
        const list = store.get('recent', []).filter((r) => r.id !== d.id);
        list.unshift({ id: d.id, title: d.title, price: d.price, url: d.url, thumb: d.thumb });
        store.set('recent', list.slice(0, 12));
    }
    const recentSection = $('[data-recent-section]');
    if (recentSection) {
        const local = (u) => typeof u === 'string' && u.startsWith(BASE + '/') && !u.startsWith('//') && !u.includes('\\');
        const list = store.get('recent', []).filter((r) => r && local(r.url));
        if (list.length) {
            const ul = $('[data-recent-list]', recentSection);
            list.forEach((r) => ul.append(el('li', {}, el('a', { class: 'recent-card', href: r.url },
                local(r.thumb) ? el('img', { src: r.thumb, alt: '', width: 160, height: 107, loading: 'lazy' }) : el('span', { class: 'recent-noimg' }, icon('image')),
                el('span', { class: 'recent-price', text: r.price }),
                el('span', { class: 'recent-title', text: r.title }),
            ))));
            recentSection.hidden = false;
            $('[data-recent-clear]', recentSection).addEventListener('click', () => { store.del('recent'); recentSection.hidden = true; });
        }
    }

    /* ---------- Web Push: follow offices ---------- */
    const pushSupported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    const b64ToBytes = (s) => {
        const pad = '='.repeat((4 - (s.length % 4)) % 4);
        const raw = atob((s + pad).replace(/-/g, '+').replace(/_/g, '/'));
        return Uint8Array.from(raw, (c) => c.charCodeAt(0));
    };
    async function currentSub() {
        if (!pushSupported) return null;
        const reg = await navigator.serviceWorker.getRegistration(BASE + '/');
        return reg ? reg.pushManager.getSubscription() : null;
    }
    async function ensureSub() {
        if (isIOS && !isStandalone) {
            // iOS only allows web push for apps added to the Home Screen: show the 3-step guide.
            showIosGuide();
            throw Object.assign(new Error(''), { silent: true });
        }
        if (!pushSupported) throw new Error(tr('push_unsupported'));
        const perm = Notification.permission === 'default' ? await Notification.requestPermission() : Notification.permission;
        if (perm !== 'granted') throw new Error(tr('push_denied'));
        const reg = await navigator.serviceWorker.register(BASE + '/sw.js', { scope: BASE + '/' });
        await navigator.serviceWorker.ready;
        const existing = await reg.pushManager.getSubscription();
        if (existing) return existing;
        // Some browsers (or blocked push services) never settle: fail clearly instead of hanging.
        return Promise.race([
            reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(meta('vapid-key')) }),
            new Promise((_, rej) => setTimeout(() => rej(new Error(tr('push_unsupported'))), 15000)),
        ]);
    }
    let statusPromise = null;
    const fetchStatus = async () => {
        const sub = await currentSub();
        if (!sub) return { offices: [], feed: [], site_news: false, subscribed: false };
        return postJSON('/push/status', { endpoint: sub.endpoint });
    };
    const getStatus = () => (statusPromise ??= fetchStatus().catch(() => ({ offices: [], feed: [] })));

    const followWidgets = $$('[data-follow-office]');
    function paintFollow(w, following, count) {
        const btn = $('[data-follow-btn]', w);
        btn.setAttribute('aria-pressed', String(following));
        btn.classList.toggle('btn-primary', !following);
        btn.classList.toggle('btn-outline', following);
        $('[data-follow-label]', w).textContent = following ? tr('following') : tr('follow');
        if (count !== undefined) $('[data-follow-count]', w).textContent = count;
    }
    if (followWidgets.length) {
        getStatus().then((st) => {
            const ids = new Set((st.offices || []).map((o) => String(o.id)));
            followWidgets.forEach((w) => paintFollow(w, ids.has(w.dataset.followOffice)));
        });
        followWidgets.forEach((w) => $('[data-follow-btn]', w).addEventListener('click', async (e) => {
            const btn = e.currentTarget;
            const officeId = Number(w.dataset.followOffice);
            const following = btn.getAttribute('aria-pressed') === 'true';
            btn.disabled = true;
            try {
                if (following) {
                    const sub = await currentSub();
                    const r = await postJSON('/push/unfollow', { office_id: officeId, endpoint: sub ? sub.endpoint : '' });
                    paintFollow(w, false, r.followers);
                    toast(tr('unfollowed_toast'));
                } else {
                    const sub = await ensureSub();
                    const r = await postJSON('/push/follow', { office_id: officeId, subscription: sub.toJSON() });
                    paintFollow(w, true, r.followers);
                    toast(tr('followed_toast'));
                }
            } catch (err) {
                if (!err.silent) toast(err.message && err.message !== 'request' ? err.message : tr('push_unsupported'));
            } finally {
                btn.disabled = false;
            }
        }));
    }

    /* ---------- Notification centre ---------- */
    const center = $('[data-notif-center]');
    if (center) {
        const list = $('[data-follow-list]', center);
        const feed = $('[data-feed]', center);
        const news = $('[data-news-toggle]', center);
        if (!pushSupported || (isIOS && !isStandalone)) {
            const w = $('[data-push-unsupported]', center);
            w.hidden = false;
            if (isIOS && !isStandalone) $('[data-push-unsupported-text]', w).textContent = tr('push_ios');
            news.disabled = !(isIOS && !isStandalone);   // on iOS the toggle opens the install guide
        }
        const paint = (st) => {
            news.checked = Boolean(st.site_news);
            list.replaceChildren();
            if (!st.offices?.length) list.append(el('li', { class: 'muted', text: tr('no_follows') }));
            (st.offices || []).forEach((o) => {
                const unf = el('button', { type: 'button', class: 'btn btn-ghost btn-sm text-danger', text: tr('unfollow') });
                unf.addEventListener('click', async () => {
                    const sub = await currentSub();
                    await postJSON('/push/unfollow', { office_id: o.id, endpoint: sub ? sub.endpoint : '' }).catch(() => {});
                    statusPromise = null;
                    paint(await getStatus());
                    toast(tr('unfollowed_toast'));
                });
                list.append(el('li', {},
                    el('a', { href: o.url, class: 'follow-item' },
                        o.logo ? el('img', { src: o.logo, alt: '', width: 40, height: 40 }) : el('span', { class: 'avatar avatar-sm', text: (o.name || '?').slice(0, 1) }),
                        el('span', { text: o.name })),
                    unf));
            });
            const sl = $('[data-search-list]', center);
            if (sl) {
                sl.replaceChildren();
                if (!st.searches?.length) sl.append(el('li', { class: 'muted', text: tr('no_searches') }));
                (st.searches || []).forEach((q) => {
                    const del = el('button', { type: 'button', class: 'btn btn-ghost btn-sm text-danger', text: tr('delete') });
                    del.addEventListener('click', async () => {
                        const sub = await currentSub();
                        await postJSON('/push/search/delete', { id: q.id, endpoint: sub ? sub.endpoint : '' }).catch(() => {});
                        statusPromise = null;
                        paint(await getStatus());
                    });
                    sl.append(el('li', {},
                        el('a', { href: q.url, class: 'follow-item' }, icon('search'), el('span', { text: q.label }),
                            q.hits ? el('span', { class: 'badge', text: String(q.hits) }) : null),
                        del));
                });
            }
            feed.replaceChildren();
            if (!st.feed?.length) feed.append(el('li', { class: 'muted', text: tr('no_feed') }));
            (st.feed || []).forEach((n) => feed.append(el('li', {},
                el('p', {}, el('a', { href: n.url }, el('strong', { text: n.title }))),
                el('p', { class: 'muted small', text: n.body }),
                el('p', { class: 'muted small', text: n.date }))));
        };
        getStatus().then(paint);
        news.addEventListener('change', async () => {
            news.disabled = true;
            try {
                if (news.checked) {
                    const sub = await ensureSub();
                    await postJSON('/push/news', { enabled: true, subscription: sub.toJSON() });
                    toast(tr('news_on'));
                } else {
                    const sub = await currentSub();
                    await postJSON('/push/news', { enabled: false, endpoint: sub ? sub.endpoint : '' });
                    toast(tr('news_off'));
                }
                statusPromise = null;
                paint(await getStatus());
            } catch (err) {
                news.checked = !news.checked;
                if (!err.silent) toast(err.message);
            } finally {
                news.disabled = !pushSupported;
            }
        });
    }

    /* ---------- Save the current search as a push alert ---------- */
    $$('[data-save-search]').forEach((btn) => btn.addEventListener('click', async () => {
        let query = {};
        try { query = JSON.parse(btn.dataset.saveSearch || '{}'); } catch { /* ignore */ }
        btn.disabled = true;
        try {
            const sub = await ensureSub();
            const r = await postJSON('/push/search', { query, subscription: sub.toJSON() });
            btn.setAttribute('aria-pressed', 'true');
            $('span', btn).textContent = tr('alert_on');
            toast(r.message + ' — ' + r.label + (btn.dataset.locationNote ? ' · ' + btn.dataset.locationNote : ''));
        } catch (err) {
            if (!err.silent) toast(err.data?.error || (err.message !== 'request' ? err.message : tr('push_unsupported')));
        } finally { btn.disabled = false; }
    }));

    /* ---------- Video upload: client-side size check ---------- */
    $$('[data-video-input]').forEach((inp) => inp.addEventListener('change', () => {
        const max = Number(inp.dataset.maxBytes);
        const out = inp.closest('.field')?.querySelector('[data-video-status]');
        const big = [...inp.files].filter((f) => f.size > max).map((f) => f.name);
        const mb = (n) => (n / 1048576).toFixed(1) + ' MB';
        if (out) out.textContent = [...inp.files].map((f) => `${f.name} (${mb(f.size)})`).join(' · ') + (big.length ? ' — ' + tr('file_too_big', { name: big.join(', ') }) : '');
        inp.setCustomValidity(big.length ? tr('file_too_big', { name: big.join(', ') }) : '');
    }));

    /* ---------- Location (browser Geolocation API — no external service) ---------- */
    const LOC_KEY = 'aq-loc';
    const round3 = (n) => Math.round(n * 1000) / 1000;              // ≈100 m: enough for "near me", kinder to privacy
    function getLocation({ precise = false } = {}) {
        return new Promise((resolve, reject) => {
            if (!window.isSecureContext) return reject(new Error(tr('loc_insecure')));
            if (!('geolocation' in navigator)) return reject(new Error(tr('loc_unavailable')));
            if (!precise) {
                try {
                    const c = JSON.parse(sessionStorage.getItem(LOC_KEY) || 'null');
                    if (c && Date.now() - c.t < 10 * 60 * 1000) return resolve(c);
                } catch { /* ignore */ }
            }
            navigator.geolocation.getCurrentPosition((pos) => {
                const raw = { lat: pos.coords.latitude, lng: pos.coords.longitude, acc: pos.coords.accuracy };
                const loc = precise ? { ...raw, t: Date.now() } : { lat: round3(raw.lat), lng: round3(raw.lng), acc: raw.acc, t: Date.now() };
                if (!precise) { try { sessionStorage.setItem(LOC_KEY, JSON.stringify(loc)); } catch { /* ignore */ } }
                resolve(loc);
            }, (err) => {
                reject(new Error(err.code === 1 ? tr('loc_denied') : err.code === 3 ? tr('loc_timeout') : tr('loc_unavailable')));
            }, { enableHighAccuracy: precise, timeout: precise ? 20000 : 12000, maximumAge: precise ? 0 : 5 * 60 * 1000 });
        });
    }
    const nearParam = (loc) => `${round3(loc.lat).toFixed(3)},${round3(loc.lng).toFixed(3)}`;
    const withQuery = (href, params) => {
        const u = new URL(href, location.href);
        Object.entries(params).forEach(([k, v]) => u.searchParams.set(k, v));
        return u.pathname + u.search;
    };

    // "Near me" chip on the results page
    $$('[data-near-me]').forEach((btn) => btn.addEventListener('click', async () => {
        btn.disabled = true;
        btn.classList.add('is-locating');
        try {
            const loc = await getLocation();
            location.href = withQuery(btn.dataset.target, { near: nearParam(loc), r: 5, sort: 'nearest' });
        } catch (err) {
            toast(err.message);
            btn.disabled = false;
            btn.classList.remove('is-locating');
        }
    }));

    /** Card-shaped placeholders while a row loads: the space is kept, so nothing jumps when results arrive. */
    const skeletonCards = (n) => Array.from({ length: n }, () => el('div', { class: 'sk-card', 'aria-hidden': 'true' },
        el('div', { class: 'sk sk-media' }), el('div', { class: 'sk sk-line sk-w80' }), el('div', { class: 'sk sk-line sk-w50' })));

    // Home: "Near you" row (+ city fallback) and "Recommended for you"
    const nearSec = $('[data-near-section]');
    if (nearSec) {
        const prompt = $('[data-near-prompt]', nearSec);
        const fallback = $('[data-near-fallback]', nearSec);
        const list = $('[data-near-list]', nearSec);
        const showFallback = (msg) => { prompt.hidden = true; fallback.hidden = false; if (msg) $('[data-near-msg]', fallback).textContent = msg; };
        const load = async () => {
            const btn = $('[data-near-load]', nearSec);
            if (btn) { btn.disabled = true; btn.classList.add('is-locating'); }
            try {
                const loc = await getLocation();
                list.replaceChildren(...skeletonCards(4));
                list.setAttribute('aria-busy', 'true');
                const r = await fetch(nearSec.dataset.endpoint + '?near=' + encodeURIComponent(nearParam(loc)), { headers: { Accept: 'application/json' } });
                const d = await r.json();
                list.replaceChildren();
                if (!d.ok || !d.count) { showFallback(tr('near_none')); return; }
                prompt.hidden = true;
                list.insertAdjacentHTML('beforeend', d.html);       // server-rendered, escaped markup from our own API
                paintPlaceholders(list);
                $('[data-near-title]', nearSec).textContent = d.title;
                const all = $('[data-near-all]', nearSec);
                all.href = d.url;
                all.hidden = false;
                loadReco(loc);
            } catch (err) {
                list.replaceChildren();
                showFallback(err.message + ' ' + tr('pick_city'));
            } finally {
                list.removeAttribute('aria-busy');
                if (btn) { btn.disabled = false; btn.classList.remove('is-locating'); }
            }
        };
        $('[data-near-load]', nearSec)?.addEventListener('click', load);
        if (!window.isSecureContext || !('geolocation' in navigator)) showFallback();
        else navigator.permissions?.query({ name: 'geolocation' }).then((st) => {
            if (st.state === 'granted') load();                     // already allowed: no prompt, just show results
            else if (st.state === 'denied') showFallback();
        }).catch(() => {});
    }
    let recoLoaded = false;
    function loadReco(loc) {
        const sec = $('[data-reco-section]');
        if (!sec || recoLoaded) return;
        const ids = store.get('recent', []).map((r) => Number(r.id)).filter(Boolean).slice(0, 12);
        if (!ids.length) return;
        recoLoaded = true;
        const q = '?ids=' + ids.join(',') + (loc ? '&near=' + encodeURIComponent(nearParam(loc)) : '');
        const list = $('[data-reco-list]', sec);
        list.replaceChildren(...skeletonCards(4));            // we know a row is coming: hold its place
        list.setAttribute('aria-busy', 'true');
        sec.hidden = false;
        fetch(sec.dataset.endpoint + q, { headers: { Accept: 'application/json' } }).then((r) => r.json()).then((d) => {
            list.replaceChildren();
            if (!d.ok || !d.count) { sec.hidden = true; return; }
            list.insertAdjacentHTML('beforeend', d.html);
            paintPlaceholders(list);
        }).catch(() => { sec.hidden = true; }).finally(() => list.removeAttribute('aria-busy'));
    }
    if ($('[data-reco-section]')) setTimeout(() => loadReco(null), 0);

    // Listing form: office stands at the property → precise pin
    $$('[data-use-my-location]').forEach((btn) => btn.addEventListener('click', async () => {
        btn.disabled = true;
        btn.classList.add('is-locating');
        try {
            const loc = await getLocation({ precise: true });
            const lat = $('#f-lat'), lng = $('#f-lng');
            lat.value = loc.lat.toFixed(6);
            lng.value = loc.lng.toFixed(6);
            lat.dispatchEvent(new Event('change'));
            lng.dispatchEvent(new Event('change'));
            toast(tr('loc_set', { m: Math.round(loc.acc || 0) }));
        } catch (err) { toast(err.message); } finally { btn.disabled = false; btn.classList.remove('is-locating'); }
    }));

    /* ---------- Maps (Leaflet + markercluster, self-hosted JS; configurable tile server) ---------- */
    const hasLeaflet = () => typeof window.L !== 'undefined';
    const tilesMeta = document.querySelector('meta[name="map-tiles"]');
    const tileLayer = () => {
        const layer = window.L.tileLayer(tilesMeta?.content || '', { maxZoom: 19, attribution: '' });
        layer.getAttribution = () => null;   // attribution text is added safely below (no HTML injection)
        return layer;
    };
    const addAttribution = (map) => {
        const txt = tilesMeta?.dataset.attribution;
        map.attributionControl.setPrefix(false);
        if (txt) map.attributionControl.getContainer().append(document.createTextNode(txt));
    };
    const parseCenter = (s) => (s || '36.1911,44.0092').split(',').map(Number);

    // Results map with price pins
    const resultsMap = $('[data-map-results]');
    if (resultsMap && hasLeaflet()) {
        const map = window.L.map(resultsMap, { scrollWheelZoom: true }).setView(parseCenter(resultsMap.dataset.center), 12);
        tileLayer().addTo(map);
        addAttribution(map);
        const group = typeof window.L.markerClusterGroup === 'function'
            ? window.L.markerClusterGroup({
                showCoverageOnHover: false,
                maxClusterRadius: 50,
                // getChildCount() is an integer, so this markup cannot carry user content
                iconCreateFunction: (c) => window.L.divIcon({ className: 'cluster-pin', html: `<span>${Number(c.getChildCount())}</span>`, iconSize: [44, 44] }),
            })
            : window.L.layerGroup();
        map.addLayer(group);

        // Active "near" search: show the centre and radius
        const nearPt = (resultsMap.dataset.near || '').split(',').map(Number);
        if (nearPt.length === 2 && nearPt.every(Number.isFinite) && Number(resultsMap.dataset.radius) > 0) {
            window.L.circle(nearPt, { radius: Number(resultsMap.dataset.radius) * 1000, color: '#1f5641', weight: 2, fillOpacity: 0.06, interactive: false }).addTo(map);
            window.L.marker(nearPt, { icon: window.L.divIcon({ className: 'me-pin', iconSize: [18, 18] }), keyboard: false, interactive: false }).addTo(map);
        }

        // Locate-me control
        const Locate = window.L.Control.extend({
            options: { position: 'topleft' },
            onAdd() {
                const b = el('button', { type: 'button', class: 'map-ctrl', 'aria-label': tr('locate_me'), title: tr('locate_me') }, icon('locate'));
                window.L.DomEvent.disableClickPropagation(b);
                let me = null;
                b.addEventListener('click', async () => {
                    b.classList.add('is-locating');
                    try {
                        const loc = await getLocation();
                        me?.remove();
                        me = window.L.layerGroup([
                            window.L.circle([loc.lat, loc.lng], { radius: Math.max(loc.acc || 0, 100), color: '#2563eb', weight: 1, fillOpacity: 0.1, interactive: false }),
                            window.L.marker([loc.lat, loc.lng], { icon: window.L.divIcon({ className: 'me-pin', iconSize: [18, 18] }), keyboard: false, interactive: false }),
                        ]).addTo(map);
                        map.setView([loc.lat, loc.lng], 14);
                    } catch (err) { toast(err.message); } finally { b.classList.remove('is-locating'); }
                });
                return b;
            },
        });
        map.addControl(new Locate());

        // "Search this area" after the visitor pans/zooms the map
        const areaBtn = el('button', { type: 'button', class: 'btn btn-primary btn-sm map-area-btn', hidden: true }, icon('search'), el('span', { text: tr('search_this_area') }));
        resultsMap.parentElement.append(areaBtn);
        let userMoved = false;
        map.on('dragstart zoomstart', () => { userMoved = true; });
        map.on('moveend', () => { if (userMoved) areaBtn.hidden = false; });
        areaBtn.addEventListener('click', () => {
            const c = map.getCenter();
            const corner = map.getBounds().getNorthEast();
            const km = map.distance(c, corner) / 1000;
            const radii = [1, 2, 3, 5, 10, 25, 50];
            const r = radii.find((x) => x >= km * 0.75) || 50;
            location.href = withQuery(resultsMap.dataset.searchArea, { near: nearParam({ lat: c.lat, lng: c.lng }), r, sort: 'nearest' });
        });
        fetch(resultsMap.dataset.endpoint, { headers: { Accept: 'application/json' } })
            .finally(() => resultsMap.classList.remove('is-loading'))
            .then((r) => r.json())
            .then(({ items = [] }) => {
                if (!items.length) {
                    resultsMap.after(el('p', { class: 'muted map-note', text: tr('map_empty') }));
                    if (nearPt.length === 2 && nearPt.every(Number.isFinite)) map.setView(nearPt, 12);
                    return;
                }
                const bounds = [];
                items.forEach((p) => {
                    const pin = window.L.divIcon({ className: 'price-pin', html: '', iconSize: null });
                    const m = window.L.marker([p.lat, p.lng], { icon: pin, title: p.title, keyboard: true });
                    m.on('add', () => { const n = m.getElement(); if (n && !n.firstChild) n.append(el('span', { text: p.short })); });
                    group.addLayer(m);
                    const card = el('a', { class: 'map-card', href: p.url },
                        p.thumb ? el('img', { src: p.thumb, alt: '', width: 220, height: 147 }) : null,
                        el('strong', { class: 'map-card-price', text: p.price }),
                        p.dist ? el('span', { class: 'card-distance', text: p.dist }) : null,
                        el('span', { class: 'map-card-title', text: p.title }),
                        el('span', { class: 'muted small', text: [p.type, p.beds ? `${p.beds} ${tr('beds_short')}` : '', `${Math.round(p.area)} ${tr('sqm')}`].filter(Boolean).join(' · ') }));
                    m.bindPopup(card, { maxWidth: 240, minWidth: 220 });
                    bounds.push([p.lat, p.lng]);
                });
                if (nearPt.length === 2 && nearPt.every(Number.isFinite)) bounds.push(nearPt);
                map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
            })
            .catch(() => {});
    }

    // Single listing mini map
    const mini = $('[data-mini-map]');
    if (mini && hasLeaflet()) {
        const pos = [Number(mini.dataset.lat), Number(mini.dataset.lng)];
        const map = window.L.map(mini, { scrollWheelZoom: false, dragging: !matchMedia('(pointer: coarse)').matches }).setView(pos, 15);
        tileLayer().addTo(map);
        addAttribution(map);
        window.L.marker(pos, { icon: window.L.divIcon({ className: 'dot-pin', iconSize: [22, 22] }), keyboard: false }).addTo(map);
    }

    // Location picker on the listing form
    const picker = $('[data-picker-map]');
    if (picker && hasLeaflet()) {
        const lat = $('#f-lat'), lng = $('#f-lng');
        const has = lat.value && lng.value && !Number.isNaN(Number(lat.value));
        const start = has ? [Number(lat.value), Number(lng.value)] : parseCenter(picker.dataset.center);
        const map = window.L.map(picker).setView(start, has ? 16 : 12);
        tileLayer().addTo(map);
        addAttribution(map);
        let marker = null;
        const place = (ll) => {
            if (!marker) {
                marker = window.L.marker(ll, { draggable: true, title: picker.getAttribute('aria-label') || '', icon: window.L.divIcon({ className: 'dot-pin', iconSize: [22, 22] }) }).addTo(map);
                marker.getElement()?.setAttribute('aria-label', picker.getAttribute('aria-label') || 'pin');
                marker.on('dragend', () => place(marker.getLatLng()));
            }
            marker.setLatLng(ll);
            lat.value = ll.lat.toFixed(6);
            lng.value = ll.lng.toFixed(6);
        };
        if (has) place({ lat: start[0], lng: start[1] });
        map.on('click', (e) => place(e.latlng));
        const sync = () => { const a = Number(lat.value), b = Number(lng.value); if (lat.value && lng.value && !Number.isNaN(a + b)) { place({ lat: a, lng: b }); map.setView([a, b]); } };
        lat.addEventListener('change', sync);
        lng.addEventListener('change', sync);
    }
})();
