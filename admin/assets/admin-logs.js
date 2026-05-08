/* global pfeLogs, copyToClipboard */
(function () {
    'use strict';

    var overlay = document.getElementById('pfe-log-modal-overlay');
    var modalBody = document.getElementById('pfe-log-modal-body');
    var closeBtn  = document.getElementById('pfe-log-modal-close');

    // ── Modal helpers ──────────────────────────────────────────────────────────
    function openModal(id) {
        var tpl = document.getElementById('pfe-log-tpl-' + id);
        if (!tpl || !overlay || !modalBody) return;

        modalBody.innerHTML = '';
        modalBody.appendChild(tpl.content.cloneNode(true));
        overlay.style.display = 'flex';

        var copyBtn = modalBody.querySelector('.pfe-log-copy-btn');
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                var pre = modalBody.querySelector('.pfe-log-payload-pre');
                if (pre && typeof window.copyToClipboard === 'function') {
                    window.copyToClipboard(pre.textContent.trim());
                }
            });
        }
    }

    function closeModal() {
        if (overlay) overlay.style.display = 'none';
        if (modalBody) modalBody.innerHTML = '';
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }
    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeModal();
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });

    // ── Detail button clicks (event delegation) ────────────────────────────────
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.pfe-log-detail-btn');
        if (btn) openModal(btn.dataset.id);
    });

    // ── Purge button ───────────────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.pfe-logs-purge-btn');
        if (!btn) return;

        var daysInput = document.getElementById('pfe-cleanup-days');
        var days = daysInput ? parseInt(daysInput.value, 10) || 90 : 90;
        var statusEl = document.querySelector('.pfe-logs-purge-status');

        if (!confirm('¿Borrar logs con más de ' + days + ' días? Esta acción no se puede deshacer.')) return;

        btn.disabled = true;
        if (statusEl) statusEl.textContent = '…';

        var body = new URLSearchParams({
            action: 'pfe_logs_purge',
            nonce:  pfeLogs.nonce,
            days:   days,
        });

        fetch(pfeLogs.ajaxUrl, {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    body.toString(),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                if (statusEl) statusEl.textContent = data.data.deleted + ' log(s) borrados.';
            } else {
                if (statusEl) statusEl.textContent = 'Error al borrar.';
            }
        })
        .catch(function () {
            if (statusEl) statusEl.textContent = 'Error de red.';
        })
        .finally(function () {
            btn.disabled = false;
        });
    });
})();
