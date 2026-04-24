<?php
defined('ABSPATH') || exit;

$pdfNl          = $this->settings->getPdfNewsletter();
$mappings       = $this->settings->getPdfTemplates();
$fileMappings   = $this->settings->getPdfFileMappings();
$emailTemplates = $this->settings->getPdfEmailTemplates();
$templDir       = PFE_DIR . 'templates/';

// Build SELECT options HTML for reuse in both PHP-rendered rows and JS <template>
$tplSelectOptionsHtml = '<option value="">' . esc_html__('— sin template BD —', 'popup-form-engine') . '</option>';
foreach ($emailTemplates as $et) {
    $tplSelectOptionsHtml .= '<option value="' . esc_attr($et['slug'] ?? '') . '">' . esc_html($et['name'] ?? $et['slug'] ?? '') . '</option>';
}
?>

<!-- ══ SECCIÓN 1: Newsletter PDF ════════════════════════════════════════════ -->
<h2><?php esc_html_e('Newsletter en flujo PDF', 'popup-form-engine'); ?></h2>
<table class="form-table" role="presentation">
    <tr>
        <th scope="row"><?php esc_html_e('Activar newsletter en PDF', 'popup-form-engine'); ?></th>
        <td>
            <label>
                <input type="checkbox" name="pdf_newsletter_enabled" value="1"<?php checked(!empty($pdfNl['enabled'])); ?>>
                <?php esc_html_e('Procesar suscripción newsletter al enviar un PDF', 'popup-form-engine'); ?>
            </label>
        </td>
    </tr>
</table>

<!-- ══ SECCIÓN 2: Templates de email ════════════════════════════════════════ -->
<hr>
<h2><?php esc_html_e('Templates de email PDF', 'popup-form-engine'); ?></h2>
<p class="description"><?php esc_html_e('Crea y edita los templates de email que se envían al usuario cuando solicita un PDF. Los templates se asignan a través de los mappings de las secciones inferiores.', 'popup-form-engine'); ?></p>

<!-- Hidden JSON input: JS serializes all templates here before submit -->
<input type="hidden" id="pfe-pdf-tpl-json-input" name="pfe_pdf_email_templates_json" value="">

<!-- List view -->
<div id="pfe-email-tpl-list-view">
    <?php if (empty($emailTemplates)): ?>
        <p class="pfe-no-forms-placeholder">
            <?php esc_html_e("No hay templates. Pulsa '+ Añadir template' para crear el primero.", 'popup-form-engine'); ?>
        </p>
    <?php else: ?>
        <table class="widefat striped pfe-forms-index-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Slug', 'popup-form-engine'); ?></th>
                    <th><?php esc_html_e('Nombre', 'popup-form-engine'); ?></th>
                    <th><?php esc_html_e('Subject del email', 'popup-form-engine'); ?></th>
                    <th><?php esc_html_e('Acciones', 'popup-form-engine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($emailTemplates as $idx => $tpl): ?>
                <tr>
                    <td><code><?php echo esc_html($tpl['slug'] ?? ''); ?></code></td>
                    <td><?php echo esc_html($tpl['name'] ?? ''); ?></td>
                    <td><?php echo esc_html($tpl['subject'] ?? ''); ?></td>
                    <td class="pfe-form-row-actions">
                        <button type="button" class="pfe-email-tpl-edit button button-small"
                                data-tpl-idx="<?php echo esc_attr($idx); ?>">
                            <?php esc_html_e('Editar', 'popup-form-engine'); ?>
                        </button>
                        <button type="button" class="pfe-email-tpl-duplicate button button-small"
                                data-tpl-idx="<?php echo esc_attr($idx); ?>">
                            <?php esc_html_e('Duplicar', 'popup-form-engine'); ?>
                        </button>
                        <button type="button" class="pfe-email-tpl-delete button button-small button-link-delete"
                                data-tpl-idx="<?php echo esc_attr($idx); ?>"
                                data-tpl-slug="<?php echo esc_attr($tpl['slug'] ?? ''); ?>">
                            <?php esc_html_e('Eliminar', 'popup-form-engine'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <p>
        <button type="button" id="pfe-email-tpl-add" class="button button-secondary pfe-email-tpl-add-btn">
            <?php esc_html_e('+ Añadir template', 'popup-form-engine'); ?>
        </button>
    </p>
</div>

<!-- Edit view (hidden by default, shown by JS) -->
<div id="pfe-email-tpl-edit-view" style="display:none" aria-live="polite">
    <div class="pfe-edit-breadcrumb">
        <button type="button" id="pfe-email-tpl-back" class="button button-link pfe-back-btn">
            &#8592; <?php esc_html_e('Volver al listado', 'popup-form-engine'); ?>
        </button>
    </div>
    <div id="pfe-email-tpl-card-slot"></div>
    <div class="pfe-edit-actions">
        <button type="button" id="pfe-email-tpl-save" class="button button-primary">
            <?php esc_html_e('Guardar template', 'popup-form-engine'); ?>
        </button>
        <button type="button" id="pfe-email-tpl-cancel" class="button button-secondary">
            <?php esc_html_e('Cancelar', 'popup-form-engine'); ?>
        </button>
    </div>
</div>

<!-- Template element: cloned by JS for the edit form -->
<template id="pfe-email-tpl-form-tpl">
    <div class="pfe-email-tpl-block" style="display:flex;gap:1.5rem;align-items:flex-start">

        <!-- Editor column -->
        <div style="flex:1;min-width:0">
            <table class="form-table pfe-compact-table" role="presentation">
                <tr>
                    <th><label><?php esc_html_e('Slug', 'popup-form-engine'); ?> <span class="pfe-required" aria-hidden="true">*</span></label></th>
                    <td>
                        <input type="text" data-pfe-tpl-field="slug" class="regular-text" placeholder="alta-y-rehabilitacion" required>
                        <p class="description"><?php esc_html_e('Identificador único. Usado en template_slug de los mappings y en data-template-slug del enlace PDF.', 'popup-form-engine'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e('Nombre', 'popup-form-engine'); ?></label></th>
                    <td><input type="text" data-pfe-tpl-field="name" class="regular-text" placeholder="<?php esc_attr_e('Alta y rehabilitación', 'popup-form-engine'); ?>"></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e('Subject del email', 'popup-form-engine'); ?></label></th>
                    <td><input type="text" data-pfe-tpl-field="subject" class="large-text" placeholder="<?php esc_attr_e('Tu guía sobre...', 'popup-form-engine'); ?>"></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e('HTML del email', 'popup-form-engine'); ?></label></th>
                    <td>
                        <textarea data-pfe-tpl-field="html_body" rows="20" class="large-text code"
                                  style="font-family:monospace"
                                  placeholder="<html>...</html>"></textarea>
                        <p class="description" style="margin:.3rem 0"><?php esc_html_e('Los placeholders se sustituyen por los valores enviados en el formulario PDF. Si un campo no existe en el formulario, el placeholder queda vacío.', 'popup-form-engine'); ?></p>
                        <p style="margin-top:.4rem">
                            <button type="button" class="button button-secondary pfe-tpl-load-boilerplate-btn">
                                <?php esc_html_e('Cargar plantilla base', 'popup-form-engine'); ?>
                            </button>
                            <button type="button" class="button-link pfe-tpl-clear-btn" style="margin-left:10px;color:#a00;">
                                <?php esc_html_e('Limpiar', 'popup-form-engine'); ?>
                            </button>
                        </p>
                        <p style="margin-top:.4rem">
                            <button type="button" class="button button-secondary pfe-tpl-preview-btn">
                                <?php esc_html_e('Vista previa', 'popup-form-engine'); ?>
                            </button>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Variables panel -->
        <div style="width:220px;flex-shrink:0;background:#f9f9f9;border:1px solid #ddd;border-radius:3px;padding:12px;margin-top:1rem">
            <strong style="display:block;margin-bottom:6px"><?php esc_html_e('Variables', 'popup-form-engine'); ?></strong>
            <p style="font-size:11px;color:#666;margin:0 0 8px"><?php esc_html_e('Clic para copiar', 'popup-form-engine'); ?></p>

            <p style="font-size:11px;font-weight:600;margin:0 0 4px"><?php esc_html_e('Especiales (siempre disponibles)', 'popup-form-engine'); ?></p>
            <button type="button" class="button button-small pfe-tpl-var-btn"
                    data-var="{{ title }}"
                    style="display:block;width:100%;margin-bottom:4px;text-align:left;font-family:monospace;font-size:11px">
                {{ title }}
            </button>
            <button type="button" class="button button-small pfe-tpl-var-btn"
                    data-var="{{ pdf }}"
                    style="display:block;width:100%;margin-bottom:4px;text-align:left;font-family:monospace;font-size:11px">
                {{ pdf }}
            </button>

            <p style="font-size:11px;font-weight:600;margin:8px 0 4px"><?php esc_html_e('Marca', 'popup-form-engine'); ?></p>
            <?php foreach (['empresa', 'logo', 'color_primario', 'color_secundario', 'web', 'telefono_empresa', 'email_empresa', 'aviso_legal'] as $bv): ?>
            <button type="button" class="button button-small pfe-tpl-var-btn"
                    data-var="{{ <?php echo esc_attr($bv); ?> }}"
                    style="display:block;width:100%;margin-bottom:4px;text-align:left;font-family:monospace;font-size:10px">
                {{ <?php echo esc_html($bv); ?> }}
            </button>
            <?php endforeach; ?>

            <div class="pfe-tpl-dynamic-vars" style="margin-top:8px">
                <!-- Populated by JS from pfeAdmin.pdfFormsData -->
            </div>
        </div>

        <!-- Preview modal (position:fixed, not a flex child in effect) -->
        <div class="pfe-tpl-preview-modal"
             style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:999999;align-items:center;justify-content:center">
            <div style="background:#fff;width:90vw;height:90vh;border-radius:4px;overflow:hidden;display:flex;flex-direction:column">
                <div style="padding:8px 16px;background:#f0f0f0;display:flex;align-items:center;gap:8px">
                    <strong><?php esc_html_e('Vista previa del email', 'popup-form-engine'); ?></strong>
                    <button type="button" class="button pfe-tpl-preview-close" style="margin-left:auto">
                        <?php esc_html_e('Cerrar', 'popup-form-engine'); ?>
                    </button>
                </div>
                <iframe class="pfe-tpl-preview-frame" style="flex:1;border:none;width:100%" sandbox="allow-same-origin"></iframe>
            </div>
        </div>

    </div><!-- /.pfe-email-tpl-block -->
</template>


<!-- ══ SECCIÓN 3: Mappings por slug de página ════════════════════════════════ -->
<hr>
<h2><?php esc_html_e('Mappings por slug de página', 'popup-form-engine'); ?></h2>
<p class="description"><?php esc_html_e('Primer match gana. Cada mapping puede apuntar a un template BD (preferido) y/o un archivo físico (legacy fallback).', 'popup-form-engine'); ?></p>

<table class="widefat striped" id="pfe-pdf-mappings-table">
    <thead>
        <tr>
            <th><?php esc_html_e('slug_contains', 'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Template BD', 'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Archivo físico (legacy)', 'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Acción', 'popup-form-engine'); ?></th>
        </tr>
    </thead>
    <tbody id="pfe-pdf-mappings-rows">
        <?php foreach ($mappings as $i => $entry):
            $needle    = $entry['slug_contains']  ?? '';
            $tplSlug   = $entry['template_slug']  ?? '';
            $tplFile   = $entry['template_file']  ?? '';
            $fileSlug  = $tplFile !== '' ? basename($tplFile, '.html') : '';
            $fileExists = $tplFile !== '' && file_exists($templDir . basename($tplFile));
        ?>
        <tr class="pfe-pdf-mapping-row">
            <td>
                <input type="text" name="pdf_mappings[<?php echo $i; ?>][slug_contains]"
                       value="<?php echo esc_attr($needle); ?>" class="regular-text"
                       placeholder="<?php esc_attr_e('ej: altas-y-rehabilitaciones', 'popup-form-engine'); ?>">
            </td>
            <td>
                <select name="pdf_mappings[<?php echo $i; ?>][template_slug]" class="pfe-pdf-tpl-select">
                    <?php
                    echo '<option value="">' . esc_html__('— sin template BD —', 'popup-form-engine') . '</option>';
                    foreach ($emailTemplates as $et) {
                        $etSlug = $et['slug'] ?? '';
                        $etName = $et['name'] ?? $etSlug;
                        $sel    = selected($etSlug, $tplSlug, false);
                        echo '<option value="' . esc_attr($etSlug) . '"' . $sel . '>' . esc_html($etName) . '</option>';
                    }
                    ?>
                </select>
                <?php if ($tplSlug === '' && $fileSlug !== ''): ?>
                <button type="button" class="button button-small pfe-pdf-migrate-btn"
                        data-file-slug="<?php echo esc_attr($fileSlug); ?>"
                        style="margin-top:4px">
                    <?php esc_html_e('Migrar → BD', 'popup-form-engine'); ?>
                </button>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($tplFile !== ''): ?>
                    <input type="hidden" name="pdf_mappings[<?php echo $i; ?>][template_file]"
                           value="<?php echo esc_attr($tplFile); ?>">
                    <code><?php echo esc_html($tplFile); ?></code>
                    <?php if ($fileExists): ?>
                        <span style="color:green"> ✓</span>
                    <?php else: ?>
                        <span style="color:red"> ✗ <?php esc_html_e('no encontrado', 'popup-form-engine'); ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <span style="color:#999"><?php esc_html_e('—', 'popup-form-engine'); ?></span>
                <?php endif; ?>
            </td>
            <td>
                <button type="button" class="button button-small pfe-pdf-remove-row">
                    <?php esc_html_e('Eliminar', 'popup-form-engine'); ?>
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<p>
    <button type="button" class="button" id="pfe-pdf-add-row">
        <?php esc_html_e('+ Añadir mapping', 'popup-form-engine'); ?>
    </button>
</p>

<template id="pfe-pdf-row-tpl">
    <tr class="pfe-pdf-mapping-row">
        <td>
            <input type="text" name="pdf_mappings[TPL_IDX][slug_contains]"
                   value="" class="regular-text"
                   placeholder="<?php esc_attr_e('ej: cambio-de-domicilio', 'popup-form-engine'); ?>">
        </td>
        <td>
            <select name="pdf_mappings[TPL_IDX][template_slug]" class="pfe-pdf-tpl-select">
                <?php echo $tplSelectOptionsHtml; ?>
            </select>
        </td>
        <td><span style="color:#999"><?php esc_html_e('—', 'popup-form-engine'); ?></span></td>
        <td>
            <button type="button" class="button button-small pfe-pdf-remove-row">
                <?php esc_html_e('Eliminar', 'popup-form-engine'); ?>
            </button>
        </td>
    </tr>
</template>


<!-- ══ SECCIÓN 4: Mappings por filename del PDF ══════════════════════════════ -->
<hr>
<h2><?php esc_html_e('Mappings por nombre de archivo PDF', 'popup-form-engine'); ?></h2>
<p class="description"><?php esc_html_e('Si el nombre del PDF descargado contiene el texto indicado, se usa el template asignado (tiene prioridad sobre los mappings de página).', 'popup-form-engine'); ?></p>

<table class="widefat striped" id="pfe-pdf-file-mappings-table">
    <thead>
        <tr>
            <th><?php esc_html_e('filename_contains', 'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Template BD', 'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Acción', 'popup-form-engine'); ?></th>
        </tr>
    </thead>
    <tbody id="pfe-pdf-file-mappings-rows">
        <?php foreach ($fileMappings as $i => $fm): ?>
        <tr class="pfe-pdf-file-mapping-row">
            <td>
                <input type="text" name="pdf_file_mappings[<?php echo $i; ?>][filename_contains]"
                       value="<?php echo esc_attr($fm['filename_contains'] ?? ''); ?>" class="regular-text"
                       placeholder="<?php esc_attr_e('ej: guia-alta', 'popup-form-engine'); ?>">
            </td>
            <td>
                <select name="pdf_file_mappings[<?php echo $i; ?>][template_slug]">
                    <?php
                    $selectedSlug = $fm['template_slug'] ?? '';
                    echo '<option value="">' . esc_html__('— seleccionar —', 'popup-form-engine') . '</option>';
                    foreach ($emailTemplates as $et) {
                        $etSlug = $et['slug'] ?? '';
                        $sel    = selected($etSlug, $selectedSlug, false);
                        echo '<option value="' . esc_attr($etSlug) . '"' . $sel . '>' . esc_html($et['name'] ?? $etSlug) . '</option>';
                    }
                    ?>
                </select>
            </td>
            <td>
                <button type="button" class="button button-small pfe-pdf-file-remove-row">
                    <?php esc_html_e('Eliminar', 'popup-form-engine'); ?>
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<p>
    <button type="button" class="button" id="pfe-pdf-file-add-row">
        <?php esc_html_e('+ Añadir mapping por filename', 'popup-form-engine'); ?>
    </button>
</p>

<template id="pfe-pdf-file-row-tpl">
    <tr class="pfe-pdf-file-mapping-row">
        <td>
            <input type="text" name="pdf_file_mappings[TPL_IDX][filename_contains]"
                   value="" class="regular-text"
                   placeholder="<?php esc_attr_e('ej: guia-alta', 'popup-form-engine'); ?>">
        </td>
        <td>
            <select name="pdf_file_mappings[TPL_IDX][template_slug]">
                <?php echo $tplSelectOptionsHtml; ?>
            </select>
        </td>
        <td>
            <button type="button" class="button button-small pfe-pdf-file-remove-row">
                <?php esc_html_e('Eliminar', 'popup-form-engine'); ?>
            </button>
        </td>
    </tr>
</template>


<!-- ── Files on disk (info only) ─────────────────────────────────────────── -->
<hr>
<h2><?php esc_html_e('Archivos de plantilla en /templates/', 'popup-form-engine'); ?></h2>
<?php
$onDisk     = glob($templDir . '*.html') ?: [];
$referenced = array_map(fn($e) => basename($e['template_file'] ?? ''), $mappings);
$referenced[] = 'plantilla-base.html';
?>
<?php if (empty($onDisk)): ?>
    <p><?php esc_html_e('No hay archivos HTML en /templates/.', 'popup-form-engine'); ?></p>
<?php else: ?>
<table class="widefat striped">
    <thead>
        <tr>
            <th><?php esc_html_e('Archivo', 'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Importado a BD', 'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Referenciado en mappings de página', 'popup-form-engine'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        $importedSlugs = array_column($emailTemplates, 'slug');
        foreach ($onDisk as $tpl):
            $basename = basename($tpl);
            $slug     = basename($tpl, '.html');
            $imported = in_array($slug, $importedSlugs, true);
            $refed    = in_array($basename, $referenced, true);
        ?>
        <tr>
            <td><code><?php echo esc_html($basename); ?></code></td>
            <td>
                <?php if ($imported): ?>
                    <span style="color:green">&#10003; <?php esc_html_e('Sí', 'popup-form-engine'); ?></span>
                <?php else: ?>
                    <span style="color:#888">&mdash;</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($refed): ?>
                    <span style="color:green">&#10003;</span>
                <?php else: ?>
                    <span style="color:#888">&mdash; <?php esc_html_e('Sin mapping (solo accesible por slug directo)', 'popup-form-engine'); ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<script>
(function () {
    // ── Mappings por page slug ──────────────────────────────────────────────
    var tbody  = document.getElementById('pfe-pdf-mappings-rows');
    var addBtn = document.getElementById('pfe-pdf-add-row');
    var tpl    = document.getElementById('pfe-pdf-row-tpl');
    if (tbody && addBtn && tpl) {
        var rowCount = tbody.querySelectorAll('.pfe-pdf-mapping-row').length;

        addBtn.addEventListener('click', function () {
            var node = tpl.content.cloneNode(true);
            node.querySelectorAll('[name]').forEach(function (el) {
                el.name = el.name.replace('TPL_IDX', rowCount);
            });
            rowCount++;
            tbody.appendChild(node);
        });

        tbody.addEventListener('click', function (e) {
            var removeBtn = e.target.closest('.pfe-pdf-remove-row');
            if (removeBtn) {
                var row = removeBtn.closest('.pfe-pdf-mapping-row');
                if (row) row.remove();
            }
            var migrateBtn = e.target.closest('.pfe-pdf-migrate-btn');
            if (migrateBtn) {
                var fileSlug = migrateBtn.dataset.fileSlug || '';
                var select   = migrateBtn.closest('td').querySelector('.pfe-pdf-tpl-select');
                if (select && fileSlug) {
                    for (var i = 0; i < select.options.length; i++) {
                        if (select.options[i].value === fileSlug) {
                            select.selectedIndex = i;
                            migrateBtn.textContent = '✓ Migrado';
                            migrateBtn.disabled = true;
                            break;
                        }
                    }
                }
            }
        });
    }

    // ── Mappings por filename del PDF ───────────────────────────────────────
    var ftbody  = document.getElementById('pfe-pdf-file-mappings-rows');
    var faddBtn = document.getElementById('pfe-pdf-file-add-row');
    var ftpl    = document.getElementById('pfe-pdf-file-row-tpl');
    if (ftbody && faddBtn && ftpl) {
        var frowCount = ftbody.querySelectorAll('.pfe-pdf-file-mapping-row').length;

        faddBtn.addEventListener('click', function () {
            var node = ftpl.content.cloneNode(true);
            node.querySelectorAll('[name]').forEach(function (el) {
                el.name = el.name.replace('TPL_IDX', frowCount);
            });
            frowCount++;
            ftbody.appendChild(node);
        });

        ftbody.addEventListener('click', function (e) {
            var btn = e.target.closest('.pfe-pdf-file-remove-row');
            if (btn) {
                var row = btn.closest('.pfe-pdf-file-mapping-row');
                if (row) row.remove();
            }
        });
    }
})();
</script>
