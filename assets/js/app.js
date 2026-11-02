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
    $$('[data-color]').forEach((n) => { if (/^#[0-9a-f]{6}$/.test(n.dataset.color)) n.style.backgroundColor = n.dataset.color; });

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

    /* ---------- Image upload: drag & drop + previews ---------- */
    const dz = $('[data-dropzone]');
    if (dz) {
        const input = $('input[type=file]', dz);
        const preview = $('[data-preview]');
        const status = $('#upload-status');
        const maxBytes = Number(input.dataset.maxBytes);
        const maxFiles = Number(input.dataset.maxFiles);
        let urls = [];
        const render = () => {
            urls.forEach(URL.revokeObjectURL);
            urls = [];
            preview.replaceChildren();
            const files = [...input.files];
            const msgs = [tr('files_selected', { n: files.length })];
            if (files.length > maxFiles) msgs.push(tr('too_many_files', { n: maxFiles }));
            files.forEach((f) => {
                const u = URL.createObjectURL(f);
                urls.push(u);
                const li = el('li', {}, el('img', { src: u, alt: f.name }));
                if (f.size > maxBytes) { li.classList.add('is-bad'); msgs.push(tr('file_too_big', { name: f.name })); }
                preview.append(li);
            });
            status.textContent = files.length ? msgs.join(' · ') : '';
        };
        input.addEventListener('change', render);
        ['dragenter', 'dragover'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.add('is-dragover'); }));
        ['dragleave', 'drop'].forEach((ev) => dz.addEventListener(ev, () => dz.classList.remove('is-dragover')));
        dz.addEventListener('drop', (e) => {
            e.preventDefault();
            const dt = new DataTransfer();
            [...e.dataTransfer.files].filter((f) => f.type.startsWith('image/')).forEach((f) => dt.items.add(f));
            input.files = dt.files;
            render();
        });
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
            toast(r.message + ' — ' + r.label);
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
        fetch(resultsMap.dataset.endpoint, { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then(({ items = [] }) => {
                if (!items.length) {
                    resultsMap.after(el('p', { class: 'muted map-note', text: tr('map_empty') }));
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
                        el('span', { class: 'map-card-title', text: p.title }),
                        el('span', { class: 'muted small', text: [p.type, p.beds ? `${p.beds} ${tr('beds_short')}` : '', `${Math.round(p.area)} ${tr('sqm')}`].filter(Boolean).join(' · ') }));
                    m.bindPopup(card, { maxWidth: 240, minWidth: 220 });
                    bounds.push([p.lat, p.lng]);
                });
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
