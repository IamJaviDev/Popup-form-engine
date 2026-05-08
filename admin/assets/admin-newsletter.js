/* global pfeNewsletterTest */
(function () {
    'use strict';

    var btn      = document.querySelector('.pfe-nl-test-btn');
    var resultEl = document.querySelector('.pfe-nl-test-result');

    if (!btn || !resultEl) return;

    btn.addEventListener('click', function () {
        btn.disabled = true;
        var origText = btn.textContent;
        btn.textContent = 'Probando…';
        resultEl.style.display = 'block';
        resultEl.innerHTML = '<p>Enviando payload de prueba…</p>';

        var body = new URLSearchParams({
            action: 'pfe_newsletter_test',
            nonce:  pfeNewsletterTest.nonce,
        });

        fetch(pfeNewsletterTest.ajaxUrl, {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    body.toString(),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) {
                resultEl.innerHTML = '<div class="notice notice-error inline"><p>Error en la petición AJAX.</p></div>';
                return;
            }
            renderResult(data.data);
        })
        .catch(function (err) {
            resultEl.innerHTML = '<div class="notice notice-error inline"><p>Error de red: ' + escapeHtml(err.message) + '</p></div>';
        })
        .finally(function () {
            btn.disabled = false;
            btn.textContent = origText;
        });
    });

    function renderResult(r) {
        var icon, noticeClass, title;

        if (r.ok) {
            icon = '✅';
            noticeClass = 'notice-success';
            title = 'Conexión OK';
        } else if (r.reason === 'no_host') {
            icon = '⚠️';
            noticeClass = 'notice-warning';
            title = 'Configuración incompleta';
        } else if (r.reason === 'http_error') {
            icon = '⚠️';
            noticeClass = 'notice-warning';
            title = 'Backend respondió con error HTTP ' + r.http_code;
        } else {
            icon = '❌';
            noticeClass = 'notice-error';
            title = 'Error de red';
        }

        var html = '<div class="notice ' + noticeClass + ' inline">';
        html += '<p><strong>' + icon + ' ' + escapeHtml(title) + '</strong>';
        if (r.elapsed_ms !== undefined) {
            html += ' <span style="color:#646970">(' + r.elapsed_ms + ' ms)</span>';
        }
        html += '</p>';
        if (r.message) {
            html += '<p>' + escapeHtml(r.message) + '</p>';
        }
        html += '</div>';

        html += '<details style="margin-top:.5rem;">';
        html += '<summary style="cursor:pointer;font-weight:600;">Ver detalle completo</summary>';
        html += '<div style="margin-top:.5rem;background:#f6f7f7;padding:.75rem;border-radius:3px;">';

        if (r.url) {
            html += '<p><strong>URL:</strong> <code>' + escapeHtml(r.url) + '</code></p>';
        }
        if (r.http_code !== undefined) {
            html += '<p><strong>HTTP code:</strong> ' + r.http_code + '</p>';
        }
        if (r.payload) {
            html += '<p><strong>Payload enviado:</strong></p>';
            html += '<pre style="background:#fff;padding:.5rem;border:1px solid #c3c4c7;font-size:.8rem;overflow:auto;max-height:200px;">'
                  + escapeHtml(JSON.stringify(r.payload, null, 2)) + '</pre>';
        }
        if (r.body) {
            html += '<p><strong>Respuesta del backend:</strong></p>';
            html += '<pre style="background:#fff;padding:.5rem;border:1px solid #c3c4c7;font-size:.8rem;overflow:auto;max-height:200px;">'
                  + escapeHtml(r.body) + '</pre>';
        }

        html += '</div></details>';

        resultEl.innerHTML = html;
    }

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
