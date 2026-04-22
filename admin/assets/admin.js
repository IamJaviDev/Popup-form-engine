/* popup-form-engine admin.js — master-detail forms builder */
(function () {
    'use strict';

    // ── DOM references ───────────────────────────────────────────────────────────
    const listView  = document.getElementById('pfe-forms-list-view');
    const editView  = document.getElementById('pfe-forms-edit-view');
    const cardSlot  = document.getElementById('pfe-edit-card-slot');
    const formTpl   = document.getElementById('pfe-form-tpl');
    const fieldTpl  = document.getElementById('pfe-field-tpl');
    const jsonInput = document.getElementById('pfe-forms-json-input');
    const mainForm  = document.getElementById('pfe-settings-form');

    // Only run on the Forms tab
    if (!listView || !editView || !formTpl || !fieldTpl || !jsonInput || !mainForm) return;

    // ── State ────────────────────────────────────────────────────────────────────
    let formsArray       = Array.isArray(pfeAdmin?.formsData) ? [...pfeAdmin.formsData] : [];
    let editingIdx       = -1;      // -1 = new form
    let originalSnapshot = null;    // JSON snapshot for dirty-check

    // ── List view ────────────────────────────────────────────────────────────────

    function renderListView() {
        const placeholder = listView.querySelector('.pfe-no-forms-placeholder');
        let table         = listView.querySelector('.pfe-forms-index-table');

        if (formsArray.length === 0) {
            if (table) table.remove();
            if (!placeholder) {
                const p = document.createElement('p');
                p.className   = 'pfe-no-forms-placeholder';
                p.textContent = "No hay formularios creados. Pulsa '+ Añadir formulario' para crear el primero.";
                listView.insertBefore(p, listView.querySelector('.pfe-add-form-btn'));
            }
            return;
        }

        if (placeholder) placeholder.remove();

        if (!table) {
            table = document.createElement('table');
            table.className = 'widefat striped pfe-forms-index-table';
            table.innerHTML =
                '<thead><tr>' +
                '<th>Slug</th><th>Modo</th><th>Título del popup</th><th>Acciones</th>' +
                '</tr></thead><tbody></tbody>';
            listView.insertBefore(table, listView.querySelector('.pfe-add-form-btn'));
        }

        const tbody = table.querySelector('tbody');
        tbody.innerHTML = '';

        formsArray.forEach(function (form, idx) {
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td><code>' + escHtml(form.slug || '') + '</code></td>' +
                '<td>' + ((form.mode || 'visual') === 'html' ? 'HTML' : 'Visual') + '</td>' +
                '<td>' + escHtml(form.title || '') + '</td>' +
                '<td class="pfe-form-row-actions">' +
                    '<button type="button" class="pfe-edit-form button button-small"' +
                    ' data-form-index="' + idx + '">Editar</button> ' +
                    '<button type="button" class="pfe-delete-form button button-small button-link-delete"' +
                    ' data-form-index="' + idx + '"' +
                    ' data-form-slug="' + escAttr(form.slug || '') + '">Eliminar</button>' +
                '</td>';
            tbody.appendChild(tr);
        });
    }

    // ── Edit view ────────────────────────────────────────────────────────────────

    function openEditView(idx) {
        editingIdx = idx;
        const form = (idx >= 0 && idx < formsArray.length) ? formsArray[idx] : {};

        const node  = formTpl.content.cloneNode(true);
        const block = node.querySelector('.pfe-form-block');

        setField(block, 'slug',         form.slug                || '');
        setField(block, 'title',        form.title               || '');
        setField(block, 'subtitle',     form.subtitle            || '');
        setField(block, 'submit_text',  form.submit_button_text  || '');
        setField(block, 'success_msg',  form.success_message     || '');
        setField(block, 'html_content', form.html_content        || '');

        const modeEl = block.querySelector('[data-pfe-field="mode"]');
        if (modeEl) {
            modeEl.value = form.mode || 'visual';
            toggleModeSection(block, modeEl.value);
            modeEl.addEventListener('change', function () {
                toggleModeSection(block, this.value);
            });
        }

        // Newsletter
        setFieldChecked(block, 'nl_enabled',    !!form.newsletter_enabled);
        setFieldChecked(block, 'nl_prechecked', !!form.newsletter_pre_checked);
        setFieldChecked(block, 'nl_required',   !!form.newsletter_required);
        setField(block, 'nl_label',    form.newsletter_label    || '');
        const nlPos = block.querySelector('[data-pfe-field="nl_position"]');
        if (nlPos) nlPos.value = form.newsletter_position || 'before_submit';

        // Callback ("Llámame")
        setFieldChecked(block, 'callback_enabled',          !!form.callback_enabled);
        setField(block, 'callback_label',                   form.callback_label                  || '');
        setField(block, 'callback_email_recipients',        form.callback_email_recipients       || '');

        // Newsletter section: show/hide details based on enabled state
        (function () {
            var nlCb = block.querySelector('[data-pfe-field="nl_enabled"]');
            var nlDt = block.querySelector('.pfe-nl-details');
            if (!nlCb || !nlDt) return;
            nlDt.style.display = nlCb.checked ? '' : 'none';
            nlCb.addEventListener('change', function () { nlDt.style.display = this.checked ? '' : 'none'; });
        }());

        // Callback section: show/hide details based on enabled state
        (function () {
            var cbCb = block.querySelector('[data-pfe-field="callback_enabled"]');
            var cbDt = block.querySelector('.pfe-callback-details');
            if (!cbCb || !cbDt) return;
            cbDt.style.display = cbCb.checked ? '' : 'none';
            cbCb.addEventListener('change', function () { cbDt.style.display = this.checked ? '' : 'none'; });
        }());

        // Fields list
        const fieldsList = block.querySelector('.pfe-fields-list');
        if (fieldsList && Array.isArray(form.fields)) {
            form.fields.forEach(function (field) {
                fieldsList.appendChild(buildFieldRow(field));
            });
        }

        // New form: auto-add the primary email field (locked name, non-deletable).
        if (idx === -1 && fieldsList) {
            fieldsList.appendChild(buildFieldRow({
                type: 'email', name: 'email', label: 'Email', required: true, is_primary_email: true,
            }));
        }

        block.querySelector('.pfe-add-field').addEventListener('click', function () {
            fieldsList.appendChild(buildFieldRow({}));
        });

        cardSlot.innerHTML = '';
        cardSlot.appendChild(block);

        originalSnapshot = JSON.stringify(collectEditData());

        listView.style.display = 'none';
        editView.style.display = '';
        window.scrollTo(0, 0);
    }

    function closeEditView(force) {
        if (!force) {
            const current = JSON.stringify(collectEditData());
            if (current !== originalSnapshot) {
                if (!window.confirm('¿Descartar los cambios no guardados?')) return;
            }
        }
        editView.style.display = 'none';
        listView.style.display = '';
        cardSlot.innerHTML     = '';
        editingIdx             = -1;
    }

    // ── Data collection ──────────────────────────────────────────────────────────

    function collectEditData() {
        const block = cardSlot.querySelector('.pfe-form-block');
        if (!block) return {};

        const fields = [];
        block.querySelectorAll('.pfe-field-row').forEach(function (row) {
            fields.push({
                type:             getFF(row, 'type'),
                name:             getFF(row, 'name'),
                label:            getFF(row, 'label'),
                placeholder:      getFF(row, 'placeholder'),
                required:         getFFChecked(row, 'required'),
                options_raw:      getFF(row, 'options_raw'),
                is_primary_email: row.dataset.pfePrimary === '1',
            });
        });

        return {
            slug:                       getField(block, 'slug'),
            title:                      getField(block, 'title'),
            subtitle:                   getField(block, 'subtitle'),
            mode:                       getField(block, 'mode'),
            submit_button_text:         getField(block, 'submit_text'),
            success_message:            getField(block, 'success_msg'),
            html_content:               getField(block, 'html_content'),
            newsletter_enabled:         getFieldChecked(block, 'nl_enabled'),
            newsletter_pre_checked:     getFieldChecked(block, 'nl_prechecked'),
            newsletter_required:        getFieldChecked(block, 'nl_required'),
            newsletter_label:           getField(block, 'nl_label'),
            newsletter_position:        getField(block, 'nl_position'),
            callback_enabled:           getFieldChecked(block, 'callback_enabled'),
            callback_label:             getField(block, 'callback_label'),
            callback_email_recipients:  getField(block, 'callback_email_recipients'),
            fields,
        };
    }

    // ── Save ─────────────────────────────────────────────────────────────────────

    function saveCurrentForm() {
        const data = collectEditData();

        if (!data.slug) {
            alert('El slug es obligatorio.');
            cardSlot.querySelector('[data-pfe-field="slug"]')?.focus();
            return;
        }

        // Visual mode must always have a field with type=email and name=email.
        if (data.mode === 'visual') {
            const hasEmail = (data.fields || []).some(
                function (f) { return f.type === 'email' && f.name === 'email'; }
            );
            if (!hasEmail) {
                alert('El formulario visual debe incluir un campo de tipo "email" con name="email".');
                return;
            }
        }

        const duplicate = formsArray.some(function (f, i) {
            return f.slug === data.slug && i !== editingIdx;
        });
        if (duplicate) {
            alert('Ya existe un formulario con ese slug: "' + data.slug + '".');
            return;
        }

        if (editingIdx >= 0) {
            formsArray[editingIdx] = data;
        } else {
            formsArray.push(data);
        }

        jsonInput.value = JSON.stringify(formsArray);
        mainForm.submit();
    }

    function deleteForm(idx, slug) {
        if (!window.confirm('¿Eliminar el formulario "' + escHtml(slug) + '"?')) return;
        formsArray.splice(idx, 1);
        jsonInput.value = JSON.stringify(formsArray);
        mainForm.submit();
    }

    // ── Field row builder ────────────────────────────────────────────────────────

    function buildFieldRow(field) {
        const node = fieldTpl.content.cloneNode(true);
        const row  = node.querySelector('.pfe-field-row');

        // Mark the primary email field so applyEmailLock can distinguish it
        // from other email-type fields the user might add manually.
        if (field.is_primary_email) {
            row.dataset.pfePrimary = '1';
        }

        const typeEl = row.querySelector('[data-pfe-ff="type"]');
        if (typeEl) {
            typeEl.value = field.type || 'text';
            applyEmailLock(row, typeEl.value);
            applySelectOptions(row, typeEl.value);
            typeEl.addEventListener('change', function () {
                applyEmailLock(row, this.value);
                applySelectOptions(row, this.value);
            });
        }

        setFF(row, 'name',        field.name        || '');
        setFF(row, 'label',       field.label       || '');
        setFF(row, 'placeholder', field.placeholder || '');
        setFFChecked(row, 'required', !!field.required);

        // Restore select options: convert saved {value: label} object to textarea string.
        if (field.type === 'select' && field.options && typeof field.options === 'object') {
            const raw = Object.entries(field.options)
                .map(function (e) { return e[0] === e[1] ? e[0] : e[0] + ':' + e[1]; })
                .join('\n');
            setFF(row, 'options_raw', raw);
        } else if (field.options_raw) {
            setFF(row, 'options_raw', field.options_raw);
        }

        row.querySelector('.pfe-remove-field').addEventListener('click', function () {
            row.remove();
        });

        return row;
    }

    /**
     * Lock behaviour only applies to the primary email field (data-pfe-primary="1").
     * Other email-type fields the user adds manually remain fully editable and deletable.
     */
    function applyEmailLock(row, type) {
        const isPrimary = row.dataset.pfePrimary === '1';
        const nameEl    = row.querySelector('[data-pfe-ff="name"]');
        const removeBtn = row.querySelector('.pfe-remove-field');

        if (type === 'email' && isPrimary) {
            if (nameEl)    { nameEl.value = 'email'; nameEl.readOnly = true; }
            if (removeBtn)   removeBtn.style.display = 'none';
        } else {
            if (nameEl && nameEl.readOnly) nameEl.readOnly = false;
            if (removeBtn)                 removeBtn.style.display = '';
        }
    }

    /** Show/hide the options textarea for select-type fields. */
    function applySelectOptions(row, type) {
        const wrap = row.querySelector('.pfe-field-options-wrap');
        if (wrap) wrap.style.display = type === 'select' ? '' : 'none';
    }

    // ── Toggle visual/html sections ──────────────────────────────────────────────

    function toggleModeSection(block, mode) {
        const visual = block.querySelector('.pfe-visual-mode-section');
        const html   = block.querySelector('.pfe-html-mode-section');
        if (visual) visual.style.display = mode === 'visual' ? '' : 'none';
        if (html)   html.style.display   = mode === 'html'   ? '' : 'none';
    }

    // ── Accessor helpers ─────────────────────────────────────────────────────────

    function getField(parent, field) {
        const el = parent.querySelector('[data-pfe-field="' + field + '"]');
        if (!el) return '';
        return el.type === 'checkbox' ? el.checked : el.value;
    }

    function getFieldChecked(parent, field) {
        const el = parent.querySelector('[data-pfe-field="' + field + '"]');
        return el ? el.checked : false;
    }

    function setField(parent, field, value) {
        const el = parent.querySelector('[data-pfe-field="' + field + '"]');
        if (!el) return;
        el.value = value;
    }

    function setFieldChecked(parent, field, checked) {
        const el = parent.querySelector('[data-pfe-field="' + field + '"]');
        if (el) el.checked = checked;
    }

    function getFF(row, field) {
        const el = row.querySelector('[data-pfe-ff="' + field + '"]');
        return el ? el.value : '';
    }

    function getFFChecked(row, field) {
        const el = row.querySelector('[data-pfe-ff="' + field + '"]');
        return el ? el.checked : false;
    }

    function setFF(row, field, value) {
        const el = row.querySelector('[data-pfe-ff="' + field + '"]');
        if (el) el.value = value;
    }

    function setFFChecked(row, field, checked) {
        const el = row.querySelector('[data-pfe-ff="' + field + '"]');
        if (el) el.checked = checked;
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escAttr(str) {
        return escHtml(str).replace(/'/g, '&#39;');
    }

    // ── Event wiring ─────────────────────────────────────────────────────────────

    listView.addEventListener('click', function (e) {
        const editBtn   = e.target.closest('.pfe-edit-form');
        const deleteBtn = e.target.closest('.pfe-delete-form');
        const addBtn    = e.target.closest('.pfe-add-form-btn');

        if (editBtn) {
            openEditView(parseInt(editBtn.dataset.formIndex, 10));
        } else if (deleteBtn) {
            deleteForm(
                parseInt(deleteBtn.dataset.formIndex, 10),
                deleteBtn.dataset.formSlug || ''
            );
        } else if (addBtn) {
            openEditView(-1);
        }
    });

    document.getElementById('pfe-back-to-list').addEventListener('click', function () {
        closeEditView(false);
    });
    document.getElementById('pfe-cancel-edit').addEventListener('click', function () {
        closeEditView(false);
    });
    document.getElementById('pfe-save-form').addEventListener('click', saveCurrentForm);

})();

// ── PDF forms builder ──────────────────────────────────────────────────────────
(function () {
    'use strict';

    // ── DOM references ───────────────────────────────────────────────────────────
    const pdfListView  = document.getElementById('pfe-pdf-forms-list-view');
    const pdfEditView  = document.getElementById('pfe-pdf-forms-edit-view');
    const pdfCardSlot  = document.getElementById('pfe-pdf-edit-card-slot');
    const pdfFormTpl   = document.getElementById('pfe-pdf-form-tpl');
    const pdfFieldTpl  = document.getElementById('pfe-field-tpl');
    const pdfJsonInput = document.getElementById('pfe-pdf-forms-json-input');
    const pdfMainForm  = document.getElementById('pfe-settings-form');

    if (!pdfListView || !pdfEditView || !pdfFormTpl || !pdfFieldTpl || !pdfJsonInput || !pdfMainForm) return;

    // ── State ────────────────────────────────────────────────────────────────────
    let pdfFormsArray    = Array.isArray(pfeAdmin?.pdfFormsData) ? [...pfeAdmin.pdfFormsData] : [];
    let pdfEditingIdx    = -1;
    let pdfOrigSnapshot  = null;

    // ── List view ────────────────────────────────────────────────────────────────

    function renderPdfListView() {
        const placeholder = pdfListView.querySelector('.pfe-no-forms-placeholder');
        let table         = pdfListView.querySelector('.pfe-forms-index-table');

        if (pdfFormsArray.length === 0) {
            if (table) table.remove();
            if (!placeholder) {
                const p = document.createElement('p');
                p.className   = 'pfe-no-forms-placeholder';
                p.textContent = "No hay formularios PDF. Pulsa '+ Añadir formulario PDF' para crear el primero.";
                pdfListView.insertBefore(p, pdfListView.querySelector('#pfe-pdf-add-form'));
            }
            return;
        }

        if (placeholder) placeholder.remove();

        if (!table) {
            table = document.createElement('table');
            table.className = 'widefat striped pfe-forms-index-table';
            table.innerHTML =
                '<thead><tr>' +
                '<th>Slug</th><th>Título del popup</th><th>Acciones</th>' +
                '</tr></thead><tbody></tbody>';
            pdfListView.insertBefore(table, pdfListView.querySelector('#pfe-pdf-add-form'));
        }

        const tbody = table.querySelector('tbody');
        tbody.innerHTML = '';

        pdfFormsArray.forEach(function (form, idx) {
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td><code>' + pdfEscHtml(form.slug || '') + '</code></td>' +
                '<td>' + pdfEscHtml(form.title || '') + '</td>' +
                '<td class="pfe-form-row-actions">' +
                    '<button type="button" class="pfe-pdf-edit-form button button-small"' +
                    ' data-pdf-form-index="' + idx + '">Editar</button> ' +
                    '<button type="button" class="pfe-pdf-delete-form button button-small button-link-delete"' +
                    ' data-pdf-form-index="' + idx + '"' +
                    ' data-pdf-form-slug="' + pdfEscAttr(form.slug || '') + '">Eliminar</button>' +
                '</td>';
            tbody.appendChild(tr);
        });
    }

    // ── Edit view ────────────────────────────────────────────────────────────────

    function openPdfEditView(idx) {
        pdfEditingIdx = idx;
        const form = (idx >= 0 && idx < pdfFormsArray.length) ? pdfFormsArray[idx] : {};

        const node  = pdfFormTpl.content.cloneNode(true);
        const block = node.querySelector('.pfe-form-block');

        setPdfField(block, 'slug',        form.slug            || '');
        setPdfField(block, 'title',       form.title           || '');
        setPdfField(block, 'success_msg', form.success_message || '');

        setPdfFieldChecked(block, 'nl_enabled',    !!form.newsletter_enabled);
        setPdfField(block, 'nl_label',             form.newsletter_label    || '');
        setPdfFieldChecked(block, 'nl_prechecked', !!form.newsletter_pre_checked);

        // Newsletter toggle
        (function () {
            var nlCb = block.querySelector('[data-pfe-pdf-field="nl_enabled"]');
            var nlDt = block.querySelector('.pfe-pdf-nl-details');
            if (!nlCb || !nlDt) return;
            nlDt.style.display = nlCb.checked ? '' : 'none';
            nlCb.addEventListener('change', function () { nlDt.style.display = this.checked ? '' : 'none'; });
        }());

        // Fields list
        const fieldsList = block.querySelector('.pfe-pdf-fields-list');
        if (fieldsList && Array.isArray(form.fields)) {
            form.fields.forEach(function (field) {
                fieldsList.appendChild(buildPdfFieldRow(field));
            });
        }

        // New form: auto-add primary email field
        if (idx === -1 && fieldsList) {
            fieldsList.appendChild(buildPdfFieldRow({
                type: 'email', name: 'email', label: 'Email', required: true, is_primary_email: true,
            }));
        }

        block.querySelector('.pfe-pdf-add-field').addEventListener('click', function () {
            fieldsList.appendChild(buildPdfFieldRow({}));
        });

        pdfCardSlot.innerHTML = '';
        pdfCardSlot.appendChild(block);

        pdfOrigSnapshot = JSON.stringify(collectPdfEditData());

        pdfListView.style.display = 'none';
        pdfEditView.style.display = '';
        window.scrollTo(0, 0);
    }

    function closePdfEditView(force) {
        if (!force) {
            const current = JSON.stringify(collectPdfEditData());
            if (current !== pdfOrigSnapshot) {
                if (!window.confirm('¿Descartar los cambios no guardados?')) return;
            }
        }
        pdfEditView.style.display = 'none';
        pdfListView.style.display = '';
        pdfCardSlot.innerHTML     = '';
        pdfEditingIdx             = -1;
    }

    // ── Data collection ──────────────────────────────────────────────────────────

    function collectPdfEditData() {
        const block = pdfCardSlot.querySelector('.pfe-form-block');
        if (!block) return {};

        const fields = [];
        block.querySelectorAll('.pfe-field-row').forEach(function (row) {
            fields.push({
                type:             getPdfFF(row, 'type'),
                name:             getPdfFF(row, 'name'),
                label:            getPdfFF(row, 'label'),
                placeholder:      getPdfFF(row, 'placeholder'),
                required:         getPdfFFChecked(row, 'required'),
                is_primary_email: row.dataset.pfePrimary === '1',
            });
        });

        return {
            slug:                   getPdfField(block, 'slug'),
            title:                  getPdfField(block, 'title'),
            success_message:        getPdfField(block, 'success_msg'),
            newsletter_enabled:     getPdfFieldChecked(block, 'nl_enabled'),
            newsletter_label:       getPdfField(block, 'nl_label'),
            newsletter_pre_checked: getPdfFieldChecked(block, 'nl_prechecked'),
            fields,
        };
    }

    // ── Save / Delete ─────────────────────────────────────────────────────────────

    function savePdfCurrentForm() {
        const data = collectPdfEditData();

        if (!data.slug) {
            alert('El slug es obligatorio.');
            pdfCardSlot.querySelector('[data-pfe-pdf-field="slug"]')?.focus();
            return;
        }

        const hasEmail = (data.fields || []).some(
            function (f) { return f.type === 'email' && f.name === 'email'; }
        );
        if (!hasEmail) {
            alert('El formulario PDF debe incluir un campo de tipo "email" con name="email".');
            return;
        }

        const duplicate = pdfFormsArray.some(function (f, i) {
            return f.slug === data.slug && i !== pdfEditingIdx;
        });
        if (duplicate) {
            alert('Ya existe un formulario PDF con ese slug: "' + data.slug + '".');
            return;
        }

        if (pdfEditingIdx >= 0) {
            pdfFormsArray[pdfEditingIdx] = data;
        } else {
            pdfFormsArray.push(data);
        }

        pdfJsonInput.value = JSON.stringify(pdfFormsArray);
        pdfMainForm.submit();
    }

    function deletePdfForm(idx, slug) {
        if (!window.confirm('¿Eliminar el formulario PDF "' + pdfEscHtml(slug) + '"?')) return;
        pdfFormsArray.splice(idx, 1);
        pdfJsonInput.value = JSON.stringify(pdfFormsArray);
        pdfMainForm.submit();
    }

    // ── Field row builder (mirrors generic builder, reuses #pfe-field-tpl) ───────

    function buildPdfFieldRow(field) {
        const node = pdfFieldTpl.content.cloneNode(true);
        const row  = node.querySelector('.pfe-field-row');

        if (field.is_primary_email) {
            row.dataset.pfePrimary = '1';
        }

        const typeEl = row.querySelector('[data-pfe-ff="type"]');
        if (typeEl) {
            typeEl.value = field.type || 'text';
            applyPdfEmailLock(row, typeEl.value);
            applyPdfSelectOptions(row, typeEl.value);
            typeEl.addEventListener('change', function () {
                applyPdfEmailLock(row, this.value);
                applyPdfSelectOptions(row, this.value);
            });
        }

        setPdfFF(row, 'name',        field.name        || '');
        setPdfFF(row, 'label',       field.label       || '');
        setPdfFF(row, 'placeholder', field.placeholder || '');
        setPdfFFChecked(row, 'required', !!field.required);

        row.querySelector('.pfe-remove-field').addEventListener('click', function () {
            row.remove();
        });

        return row;
    }

    function applyPdfEmailLock(row, type) {
        const isPrimary = row.dataset.pfePrimary === '1';
        const nameEl    = row.querySelector('[data-pfe-ff="name"]');
        const removeBtn = row.querySelector('.pfe-remove-field');

        if (type === 'email' && isPrimary) {
            if (nameEl)    { nameEl.value = 'email'; nameEl.readOnly = true; }
            if (removeBtn)   removeBtn.style.display = 'none';
        } else {
            if (nameEl && nameEl.readOnly) nameEl.readOnly = false;
            if (removeBtn)                 removeBtn.style.display = '';
        }
    }

    function applyPdfSelectOptions(row, type) {
        const wrap = row.querySelector('.pfe-field-options-wrap');
        if (wrap) wrap.style.display = type === 'select' ? '' : 'none';
    }

    // ── Accessor helpers ─────────────────────────────────────────────────────────

    function getPdfField(parent, field) {
        const el = parent.querySelector('[data-pfe-pdf-field="' + field + '"]');
        if (!el) return '';
        return el.type === 'checkbox' ? el.checked : el.value;
    }

    function getPdfFieldChecked(parent, field) {
        const el = parent.querySelector('[data-pfe-pdf-field="' + field + '"]');
        return el ? el.checked : false;
    }

    function setPdfField(parent, field, value) {
        const el = parent.querySelector('[data-pfe-pdf-field="' + field + '"]');
        if (el) el.value = value;
    }

    function setPdfFieldChecked(parent, field, checked) {
        const el = parent.querySelector('[data-pfe-pdf-field="' + field + '"]');
        if (el) el.checked = checked;
    }

    function getPdfFF(row, field) {
        const el = row.querySelector('[data-pfe-ff="' + field + '"]');
        return el ? el.value : '';
    }

    function getPdfFFChecked(row, field) {
        const el = row.querySelector('[data-pfe-ff="' + field + '"]');
        return el ? el.checked : false;
    }

    function setPdfFF(row, field, value) {
        const el = row.querySelector('[data-pfe-ff="' + field + '"]');
        if (el) el.value = value;
    }

    function setPdfFFChecked(row, field, checked) {
        const el = row.querySelector('[data-pfe-ff="' + field + '"]');
        if (el) el.checked = checked;
    }

    function pdfEscHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function pdfEscAttr(str) {
        return pdfEscHtml(str).replace(/'/g, '&#39;');
    }

    // ── Event wiring ─────────────────────────────────────────────────────────────

    pdfListView.addEventListener('click', function (e) {
        const editBtn   = e.target.closest('.pfe-pdf-edit-form');
        const deleteBtn = e.target.closest('.pfe-pdf-delete-form');
        const addBtn    = e.target.closest('#pfe-pdf-add-form');

        if (editBtn) {
            openPdfEditView(parseInt(editBtn.dataset.pdfFormIndex, 10));
        } else if (deleteBtn) {
            deletePdfForm(
                parseInt(deleteBtn.dataset.pdfFormIndex, 10),
                deleteBtn.dataset.pdfFormSlug || ''
            );
        } else if (addBtn) {
            openPdfEditView(-1);
        }
    });

    document.getElementById('pfe-pdf-back-to-list').addEventListener('click', function () {
        closePdfEditView(false);
    });
    document.getElementById('pfe-pdf-cancel-edit').addEventListener('click', function () {
        closePdfEditView(false);
    });
    document.getElementById('pfe-pdf-save-form').addEventListener('click', savePdfCurrentForm);

    // Initial render (JS-driven table is rebuilt after save, so PHP renders on first load)
    // Nothing needed — the PHP-rendered list is the source of truth on first load.

})();
