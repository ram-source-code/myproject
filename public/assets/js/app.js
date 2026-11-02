/* Aqarat — progressive enhancement only. Every feature works without JS. */
(() => {
    'use strict';

    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => [...r.querySelectorAll(s)];
    let i18n = {};
    try { i18n = JSON.parse($('#i18n')?.textContent || '{}'); } catch { /* ignore */ }
    const tr = (k, vars = {}) => (i18n[k] || k).replace(/:(\w+)/g, (_, n) => vars[n] ?? '');
    const csrf = () => $('input[name="_csrf"]')?.value || '';

    function toast(msg) {
        const el = document.createElement('div');
        el.className = 'toast';
        el.setAttribute('role', 'status');
        el.textContent = msg;
        document.body.append(el);
        setTimeout(() => el.remove(), 2600);
    }

    /* ---------- Mobile nav ---------- */
    const toggle = $('.nav-toggle');
    const nav = $('#primary-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', () => {
            const open = toggle.getAttribute('aria-expanded') !== 'true';
            toggle.setAttribute('aria-expanded', String(open));
            nav.classList.toggle('is-open', open);
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && nav.classList.contains('is-open')) {
                nav.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.focus();
            }
        });
    }

    /* ---------- Dismissible alerts ---------- */
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-dismiss]');
        if (btn) btn.closest('.alert')?.remove();
    });

    /* ---------- Focus error summary ---------- */
    $('[data-focus]')?.focus();

    /* ---------- Auto-submit selects ---------- */
    $$('form[data-autosubmit] select').forEach((s) => s.addEventListener('change', () => s.form.requestSubmit()));

    /* ---------- Filters panel: collapsed by default on small screens ---------- */
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
            if (confirmDlg.returnValue === 'confirm') {
                form.dataset.confirmed = '1';
                form.requestSubmit();
            }
            confirmDlg.returnValue = '';
        }, { once: true });
    }, true);

    /* Light-dismiss fallback where <dialog closedby> is unsupported */
    if (!('closedBy' in HTMLDialogElement.prototype)) {
        $$('dialog[closedby="any"]').forEach((d) => d.addEventListener('click', (e) => {
            if (e.target !== d) return;
            const r = d.getBoundingClientRect();
            const inside = e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom;
            if (!inside || d.classList.contains('lightbox')) d.close();
        }));
    }

    /* ---------- Favourites via fetch ---------- */
    document.addEventListener('submit', async (e) => {
        const form = e.target;
        if (!form.matches?.('.fav-form, .fav-form-inline')) return;
        e.preventDefault();
        const btn = $('button', form);
        btn.disabled = true;
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: new FormData(form),
                credentials: 'same-origin',
            });
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
            toast(data.label === undefined ? '' : (data.saved ? '♥' : '♡') + ' ' + data.label);
        } catch {
            form.submit();
        } finally {
            btn.disabled = false;
        }
    });

    /* ---------- Share ---------- */
    $$('[data-share]').forEach((b) => b.addEventListener('click', async () => {
        const data = { title: b.dataset.title, url: location.href };
        try {
            if (navigator.share) await navigator.share(data);
            else { await navigator.clipboard.writeText(location.href); toast(tr('link_copied')); }
        } catch { /* user cancelled */ }
    }));

    /* ---------- Password show/hide ---------- */
    $$('[data-pw-toggle]').forEach((b) => b.addEventListener('click', () => {
        const input = document.getElementById(b.getAttribute('aria-controls'));
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        b.setAttribute('aria-pressed', String(show));
        b.textContent = show ? tr('hide') : tr('show');
    }));

    /* ---------- Accessible client-side validation ----------
       Sync aria-invalid with :user-invalid so AT gets feedback at the same time as sighted users. */
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
        $$('input, select, textarea', form).forEach((el) => {
            el.addEventListener('blur', () => { el.dataset.touched = '1'; syncValidity(el); });
            el.addEventListener('input', () => syncValidity(el));
        });
        form.addEventListener('submit', (e) => {
            $$('[data-match]', form).forEach(syncValidity);
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopImmediatePropagation();
                const first = $$('input, select, textarea', form).find((el) => !el.checkValidity());
                $$('input, select, textarea', form).forEach((el) => { el.dataset.touched = '1'; if (!el.checkValidity()) el.setAttribute('aria-invalid', 'true'); });
                first?.focus();
                first?.reportValidity();
            }
        });
    });

    /* ---------- Listing form: rent period visibility, char counter ---------- */
    const purposeRadios = $$('[data-purpose]');
    const rentOnly = $('[data-rent-only]');
    if (purposeRadios.length && rentOnly) {
        const upd = () => { rentOnly.hidden = $('[data-purpose]:checked')?.value !== 'rent'; };
        purposeRadios.forEach((r) => r.addEventListener('change', upd));
        upd();
    }
    $$('[data-counter]').forEach((ta) => {
        const out = $(`[data-count-for="${ta.id}"]`);
        const upd = () => { if (out) out.textContent = ta.value.length; };
        ta.addEventListener('input', upd);
        upd();
    });

    /* ---------- Image upload: drag & drop + previews + client checks ---------- */
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
                const li = document.createElement('li');
                const img = document.createElement('img');
                const u = URL.createObjectURL(f);
                urls.push(u);
                img.src = u;
                img.alt = f.name;
                li.append(img);
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

    /* ---------- Gallery lightbox (native <dialog>) ---------- */
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
            // Preload neighbours
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

        const rtl = document.documentElement.dir === 'rtl';
        lb.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') { e.preventDefault(); show(idx + (rtl ? 1 : -1)); }
            if (e.key === 'ArrowRight') { e.preventDefault(); show(idx + (rtl ? -1 : 1)); }
        });
        // Swipe
        let x0 = null;
        lb.addEventListener('pointerdown', (e) => { if (e.target === img) x0 = e.clientX; });
        lb.addEventListener('pointerup', (e) => {
            if (x0 === null) return;
            const dx = e.clientX - x0;
            x0 = null;
            if (Math.abs(dx) > 50) show(idx + ((dx < 0) !== rtl ? 1 : -1));
        });
    }
})();
