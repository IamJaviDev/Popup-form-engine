/* popup-form-engine popup-front.js */
(function () {
    'use strict';

    if (typeof pfeData === 'undefined') return;
    const restUrl = pfeData.restUrl;

    // ── Focus / ARIA constants ────────────────────────────────────────────────────

    const FOCUSABLE_SEL = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
    ].join(',');

    let   dialogCounter = 0;
    const activeStack   = []; // { overlay, returnFocusEl }

    // ── Dialog factory ────────────────────────────────────────────────────────────

    /**
     * Creates an accessible modal dialog overlay.
     * @param {Element|null} returnFocusEl  Element that triggered the dialog; receives
     *                                      focus back when the dialog closes.
     * @returns {{ overlay, card, body, msgArea }}
     */
    function openDialog(returnFocusEl, formSlug) {
        const dlgId = 'pfe-dlg-' + (++dialogCounter);

        const overlay = document.createElement('div');
        overlay.id        = dlgId;
        overlay.className = 'pfe-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.dataset.pfeForm = formSlug || '';
        // aria-labelledby is set by setDialogTitle() once a heading is injected.

        const card = document.createElement('div');
        card.className = 'pfe-card';

        const closeBtn = document.createElement('button');
        closeBtn.className = 'pfe-close-btn';
        closeBtn.type      = 'button';
        closeBtn.setAttribute('aria-label', 'Cerrar');
        closeBtn.textContent = '×';
        closeBtn.addEventListener('click', () => closeDialog(overlay));

        // Content wrapper — all flow-specific content goes here.
        const body = document.createElement('div');
        body.className = 'pfe-card-body';

        // Aria-live region doubles as the visible message area.
        // Placed last so it renders below the form content.
        const msgArea = document.createElement('div');
        msgArea.className = 'pfe-msg-area';
        msgArea.setAttribute('aria-live', 'polite');
        msgArea.setAttribute('aria-atomic', 'true');

        card.append(closeBtn, body, msgArea);
        overlay.appendChild(card);
        document.body.appendChild(overlay);
        document.body.classList.add('pfe-scroll-locked');

        activeStack.push({ overlay, returnFocusEl: returnFocusEl || null });

        // Focus trap inside the card.
        card.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab') return;
            const els = Array.from(card.querySelectorAll(FOCUSABLE_SEL));
            if (!els.length) return;
            const first = els[0], last = els[els.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });

        // Click backdrop to close.
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeDialog(overlay);
        });

        requestAnimationFrame(function () {
            overlay.classList.add('pfe-visible');
            // Move focus into the dialog; close button is always first.
            const first = card.querySelector(FOCUSABLE_SEL);
            if (first) first.focus();
        });

        return { overlay, card, body, msgArea };
    }

    function closeDialog(overlay) {
        const idx   = activeStack.findIndex(function (o) { return o.overlay === overlay; });
        const entry = idx >= 0 ? activeStack.splice(idx, 1)[0] : null;

        overlay.classList.remove('pfe-visible');
        overlay.addEventListener('transitionend', function () { overlay.remove(); }, { once: true });

        if (!activeStack.length) {
            document.body.classList.remove('pfe-scroll-locked');
        }

        try {
            if (entry && entry.returnFocusEl && typeof entry.returnFocusEl.focus === 'function') {
                entry.returnFocusEl.focus();
            }
        } catch (_) {}
    }

    // Escape key closes the topmost dialog.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && activeStack.length) {
            closeDialog(activeStack[activeStack.length - 1].overlay);
        }
    });

    /**
     * Associates the dialog's aria-labelledby with the given heading element.
     * Generates a stable ID on the heading if it lacks one.
     */
    function setDialogTitle(overlay, headingEl) {
        if (!headingEl.id) headingEl.id = overlay.id + '-title';
        overlay.setAttribute('aria-labelledby', headingEl.id);
    }

    /**
     * Displays a message in the dialog's aria-live region (both visually and
     * announced by screen-readers).
     */
    function showMsg(card, text, type) {
        const area = card.querySelector('.pfe-msg-area');
        if (!area) return;
        area.className   = 'pfe-msg-area pfe-msg pfe-msg-' + type;
        area.textContent = text;
    }

    // ── Shared helpers ────────────────────────────────────────────────────────────

    function addLoading(container, text) {
        const el       = document.createElement('div');
        el.className   = 'pfe-loading';
        el.textContent = text || 'Cargando…';
        container.appendChild(el);
        return el;
    }

    /**
     * Toggles a submit button's disabled + loading-text state.
     * Stores original label in data-pfe-orig-text.
     */
    function setLoading(btn, loading) {
        if (!btn) return;
        btn.disabled = loading;
        if (loading) {
            if (!btn.dataset.pfeOrigText) btn.dataset.pfeOrigText = btn.textContent;
            btn.textContent = 'Enviando…';
        } else {
            btn.textContent = btn.dataset.pfeOrigText || 'Enviar';
        }
    }

    async function postJson(url, data) {
        return fetch(url, {
            method:      'POST',
            headers:     { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body:        JSON.stringify(data),
        });
    }

    function setTimestamp(form) {
        const tsEl = form.querySelector('[data-pfe-ts]');
        if (tsEl) tsEl.value = Math.floor(Date.now() / 1000);
    }

    function populateCallbackSelects(form) {
        const daySelect  = form.querySelector('.pfe-callback-day-select');
        const timeSelect = form.querySelector('.pfe-callback-time-select');
        if (!daySelect || !timeSelect) return;

        const MONTHS = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        const DAYS   = ['Domingo','Lunes','Martes','Mi\xe9rcoles','Jueves','Viernes','S\xe1bado'];
        const SLOTS_WEEKDAY  = ['09:00-11:00','11:00-13:00','16:00-18:00','18:00-20:00'];
        const SLOTS_SATURDAY = ['09:00-11:00','11:00-13:00'];

        daySelect.innerHTML = '<option value="" disabled selected>Selecciona un d\xeda</option>';
        const dates = [];
        let d = new Date();
        d.setHours(0, 0, 0, 0);
        while (dates.length < 6) {
            d = new Date(d.getTime() + 86400000);
            if (d.getDay() === 0) continue;
            dates.push(new Date(d));
        }
        dates.forEach(function (date) {
            const opt = document.createElement('option');
            const yyyy = date.getFullYear();
            const mm   = String(date.getMonth() + 1).padStart(2, '0');
            const dd   = String(date.getDate()).padStart(2, '0');
            opt.value       = yyyy + '-' + mm + '-' + dd;
            opt.textContent = DAYS[date.getDay()] + ' ' + date.getDate() + ' de ' + MONTHS[date.getMonth()];
            daySelect.appendChild(opt);
        });

        function updateTimeSelect(dayValue) {
            timeSelect.innerHTML = '<option value="" disabled selected>Selecciona una hora</option>';
            if (!dayValue) return;
            const parts = dayValue.split('-');
            const date  = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            const slots = date.getDay() === 6 ? SLOTS_SATURDAY : SLOTS_WEEKDAY;
            slots.forEach(function (slot) {
                const opt = document.createElement('option');
                opt.value = opt.textContent = slot;
                timeSelect.appendChild(opt);
            });
        }

        updateTimeSelect('');
        daySelect.addEventListener('change', function () { updateTimeSelect(this.value); });
    }

    // ── PDF popup ─────────────────────────────────────────────────────────────────

    const pdfFormConfigCache = new Map();

    const FALLBACK_PDF_FORM_HTML =
        '<div class="pfe-field-wrap">' +
            '<label for="pfe-pdf-name">Nombre <span class="pfe-required" aria-hidden="true">*</span></label>' +
            '<input type="text" id="pfe-pdf-name" name="name" class="pfe-input" required autocomplete="given-name">' +
        '</div>' +
        '<div class="pfe-field-wrap">' +
            '<label for="pfe-pdf-email">Email <span class="pfe-required" aria-hidden="true">*</span></label>' +
            '<input type="email" id="pfe-pdf-email" name="email" class="pfe-input" required autocomplete="email">' +
        '</div>' +
        '<div class="pfe-field-wrap pfe-newsletter-consent">' +
            '<label><input type="checkbox" name="pfe_newsletter_consent" value="1"> Quiero recibir novedades por email</label>' +
        '</div>' +
        '<input type="text" name="_pfe_hp" value="" autocomplete="off" aria-hidden="true" tabindex="-1" style="position:absolute;left:-9999px;width:1px;height:1px;">' +
        '<input type="hidden" name="_pfe_ts" value="" data-pfe-ts="1">' +
        '<button type="submit" class="pfe-submit-btn">Enviar</button>';

    async function openPdfPopup(pdfUrl, trigger, slug, templateSlug) {
        const pageUrl  = window.location.href;
        const cacheKey = slug || 'default';
        const { overlay, card, body } = openDialog(trigger, cacheKey);

        const loadingEl = addLoading(body);

        let config = null;
        try {
            if (pdfFormConfigCache.has(cacheKey)) {
                config = pdfFormConfigCache.get(cacheKey);
            } else {
                const res = await fetch(restUrl + 'pdf-form-config?slug=' + encodeURIComponent(cacheKey));
                if (res.ok) {
                    config = await res.json();
                    pdfFormConfigCache.set(cacheKey, config);
                }
                // 404 or non-ok → config stays null → use fallback
            }
        } catch (_err) {
            // network error → use fallback
        }

        loadingEl.remove();

        const h2 = document.createElement('h2');
        h2.textContent = (config && config.title) ? config.title : 'Recibe el PDF por email';
        body.appendChild(h2);
        setDialogTitle(overlay, h2);

        const form = document.createElement('form');
        form.className  = 'pfe-pdf-form';
        form.noValidate = true;
        form.innerHTML  = (config && config.formHtml) ? config.formHtml : FALLBACK_PDF_FORM_HTML;
        body.appendChild(form);
        setTimestamp(form);
        populateCallbackSelects(form);

        const pdfCbTrigger = form.querySelector('input[name="pfe_callback_requested"]');
        const pdfCbFields  = form.querySelector('.pfe-callback-fields');
        if (pdfCbTrigger && pdfCbFields) {
            pdfCbTrigger.addEventListener('change', function () {
                pdfCbFields.style.display = this.checked ? '' : 'none';
            });
        }

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = form.querySelector('.pfe-submit-btn');
            setLoading(btn, true);

            const fd = new FormData(form);
            fd.append('pdfUrl',        pdfUrl);
            fd.append('pageUrl',       pageUrl);
            fd.append('pdf_form_slug', cacheKey);
            if (templateSlug) fd.append('template_slug', templateSlug);

            try {
                const res  = await postJson(restUrl + 'submit-pdf', Object.fromEntries(fd));
                const data = await res.json();
                const successMsg = (res.ok && config && config.successMessage) ? config.successMessage : data.message;
                showMsg(card, successMsg, res.ok ? 'success' : 'error');
                if (res.ok) form.style.display = 'none';
                else setLoading(btn, false);
            } catch (_err) {
                showMsg(card, 'Error de conexi\xf3n. Int\xe9ntalo de nuevo.', 'error');
                setLoading(btn, false);
            }
        });
    }

    // ── Generic form popup ────────────────────────────────────────────────────────

    const formConfigCache    = new Map();
    const formConfigInflight = new Set();

    async function openGenericPopup(slug, trigger) {
        if (formConfigInflight.has(slug)) return;

        const { overlay, card, body } = openDialog(trigger, slug);
        const loadingEl = addLoading(body);

        let config;
        try {
            if (formConfigCache.has(slug)) {
                config = formConfigCache.get(slug);
            } else {
                formConfigInflight.add(slug);
                const res  = await fetch(restUrl + 'form-config?slug=' + encodeURIComponent(slug));
                formConfigInflight.delete(slug);
                const data = await res.json();
                if (!res.ok) {
                    loadingEl.textContent = data.message || 'Error al cargar el formulario.';
                    return;
                }
                config = data;
                formConfigCache.set(slug, config);
            }
        } catch (_err) {
            formConfigInflight.delete(slug);
            loadingEl.textContent = 'Error de conexi\xf3n.';
            return;
        }

        loadingEl.remove();

        if (config.title) {
            const h2 = document.createElement('h2');
            h2.textContent = config.title;
            body.appendChild(h2);
            setDialogTitle(overlay, h2);
        }
        if (config.subtitle) {
            const p       = document.createElement('p');
            p.className   = 'pfe-subtitle';
            p.textContent = config.subtitle;
            body.appendChild(p);
        }

        const form      = document.createElement('form');
        form.className  = 'pfe-generic-form';
        form.noValidate = true;
        form.innerHTML  = config.formHtml || '';

        const hiddenSlug   = document.createElement('input');
        hiddenSlug.type    = 'hidden';
        hiddenSlug.name    = 'form_slug';
        hiddenSlug.value   = slug;
        form.appendChild(hiddenSlug);
        body.appendChild(form);
        setTimestamp(form);
        populateCallbackSelects(form);

        // Callback ("Llámame") toggle: reveal day/time fields when checkbox is checked.
        const callbackTrigger = form.querySelector('input[name="pfe_callback_requested"]');
        const callbackFields  = form.querySelector('.pfe-callback-fields');
        if (callbackTrigger && callbackFields) {
            callbackTrigger.addEventListener('change', function () {
                callbackFields.style.display = this.checked ? '' : 'none';
            });
        }

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = form.querySelector('.pfe-submit-btn');
            setLoading(btn, true);

            try {
                const res  = await postJson(restUrl + 'submit-form', Object.fromEntries(new FormData(form)));
                const data = await res.json();
                showMsg(card, data.message || (res.ok ? config.successMessage : ''), res.ok ? 'success' : 'error');
                if (res.ok) form.style.display = 'none';
                else setLoading(btn, false);
            } catch (_err) {
                showMsg(card, 'Error de conexi\xf3n. Int\xe9ntalo de nuevo.', 'error');
                setLoading(btn, false);
            }
        });
    }

    // ── Click dispatcher ──────────────────────────────────────────────────────────

    document.addEventListener('click', function (e) {
        // --- PDF flow ---
        const pdfLink = e.target.closest('a');
        if (pdfLink && /\.pdf(\?|#|$)/i.test(pdfLink.href)) {
            const hasClass = pdfLink.classList.contains('pdf-popup');
            const hasImg   = !!pdfLink.querySelector('img');
            if (hasClass || hasImg) {
                e.preventDefault();
                const pdfSlug      = pdfLink.dataset.pdfFormSlug  || 'default';
                const templateSlug = pdfLink.dataset.templateSlug || null;
                openPdfPopup(pdfLink.href, pdfLink, pdfSlug, templateSlug);
                return;
            }
        }

        // --- Generic form flow ---
        const formTrigger = e.target.closest('.form-popup-trigger');
        if (formTrigger) {
            e.preventDefault();
            let slug = formTrigger.dataset.formSlug;
            if (!slug) {
                const m = (formTrigger.getAttribute('href') || '').match(/^#pfe-form:(.+)$/);
                if (m) slug = m[1];
            }
            if (slug) openGenericPopup(slug, formTrigger);
            return;
        }
    });

})();
