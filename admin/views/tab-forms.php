<?php defined('ABSPATH') || exit;
/** @var PFE_AdminPage $this */
$forms = $this->settings->getForms();
?>

<!-- ══ LIST VIEW ══════════════════════════════════════════════════════════════ -->
<div id="pfe-forms-list-view">

    <?php if (empty($forms)): ?>
        <p class="pfe-no-forms-placeholder">
            <?php esc_html_e("No hay formularios creados. Pulsa '+ Añadir formulario' para crear el primero.", 'popup-form-engine'); ?>
        </p>
    <?php else: ?>
        <table class="widefat striped pfe-forms-index-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Slug', 'popup-form-engine'); ?></th>
                    <th><?php esc_html_e('Modo', 'popup-form-engine'); ?></th>
                    <th><?php esc_html_e('Título del popup', 'popup-form-engine'); ?></th>
                    <th><?php esc_html_e('Acciones', 'popup-form-engine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($forms as $idx => $form): ?>
                    <tr>
                        <td><code><?php echo esc_html($form['slug'] ?? ''); ?></code></td>
                        <td><?php echo ($form['mode'] ?? 'visual') === 'html'
                                ? esc_html__('HTML', 'popup-form-engine')
                                : esc_html__('Visual', 'popup-form-engine'); ?></td>
                        <td><?php echo esc_html($form['title'] ?? ''); ?></td>
                        <td class="pfe-form-row-actions">
                            <button type="button"
                                    class="pfe-edit-form button button-small"
                                    data-form-index="<?php echo esc_attr($idx); ?>">
                                <?php esc_html_e('Editar', 'popup-form-engine'); ?>
                            </button>
                            <button type="button"
                                    class="pfe-delete-form button button-small button-link-delete"
                                    data-form-index="<?php echo esc_attr($idx); ?>"
                                    data-form-slug="<?php echo esc_attr($form['slug'] ?? ''); ?>">
                                <?php esc_html_e('Eliminar', 'popup-form-engine'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <button type="button" id="pfe-add-form" class="button button-secondary pfe-add-form-btn">
        <?php esc_html_e('+ Añadir formulario', 'popup-form-engine'); ?>
    </button>

</div><!-- /#pfe-forms-list-view -->


<!-- ══ EDIT VIEW ══════════════════════════════════════════════════════════════ -->
<div id="pfe-forms-edit-view" style="display:none" aria-live="polite">

    <div class="pfe-edit-breadcrumb">
        <button type="button" id="pfe-back-to-list" class="button button-link pfe-back-btn">
            &#8592; <?php esc_html_e('Volver al listado', 'popup-form-engine'); ?>
        </button>
    </div>

    <div id="pfe-edit-card-slot"></div>

    <div class="pfe-edit-actions">
        <button type="button" id="pfe-save-form" class="button button-primary">
            <?php esc_html_e('Guardar formulario', 'popup-form-engine'); ?>
        </button>
        <button type="button" id="pfe-cancel-edit" class="button button-secondary">
            <?php esc_html_e('Cancelar', 'popup-form-engine'); ?>
        </button>
    </div>

</div><!-- /#pfe-forms-edit-view -->


<!-- ══ HIDDEN INPUT: serialized forms JSON for submission ══════════════════════ -->
<input type="hidden" id="pfe-forms-json-input" name="pfe_forms_json" value="">


<!-- ══ TEMPLATE: single form block ════════════════════════════════════════════ -->
<template id="pfe-form-tpl">
    <div class="pfe-form-block">

        <!-- ── Bloque 1: Identificación ─────────────────────────────────── -->
        <div class="pfe-section">
            <div class="pfe-section-hd">
                <span class="pfe-section-title"><?php esc_html_e('Identificación', 'popup-form-engine'); ?></span>
            </div>
            <div class="pfe-section-bd">
                <table class="form-table pfe-compact-table" role="presentation">
                    <tr>
                        <th><label><?php esc_html_e('Slug', 'popup-form-engine'); ?> <span class="pfe-required" aria-hidden="true">*</span></label></th>
                        <td>
                            <input type="text" data-pfe-field="slug" class="regular-text" placeholder="mi-formulario" required>
                            <p class="description"><?php esc_html_e('Identificador único. Usado en data-form-slug del trigger.', 'popup-form-engine'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e('Título del popup', 'popup-form-engine'); ?></label></th>
                        <td><input type="text" data-pfe-field="title" class="regular-text" placeholder="<?php esc_attr_e('Título', 'popup-form-engine'); ?>"></td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e('Subtítulo', 'popup-form-engine'); ?></label></th>
                        <td><input type="text" data-pfe-field="subtitle" class="regular-text" placeholder="<?php esc_attr_e('Subtítulo opcional', 'popup-form-engine'); ?>"></td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e('Modo', 'popup-form-engine'); ?></label></th>
                        <td>
                            <select data-pfe-field="mode">
                                <option value="visual"><?php esc_html_e('Visual (campos)', 'popup-form-engine'); ?></option>
                                <option value="html"><?php esc_html_e('HTML personalizado', 'popup-form-engine'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e('Botón enviar', 'popup-form-engine'); ?></label></th>
                        <td><input type="text" data-pfe-field="submit_text" class="regular-text" placeholder="<?php esc_attr_e('Enviar', 'popup-form-engine'); ?>"></td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e('Mensaje de éxito', 'popup-form-engine'); ?></label></th>
                        <td><textarea data-pfe-field="success_msg" rows="2" class="large-text" placeholder="<?php esc_attr_e('¡Mensaje enviado!', 'popup-form-engine'); ?>"></textarea></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- ── Bloque 2a: HTML del formulario (modo HTML) ───────────────── -->
        <div class="pfe-html-mode-section pfe-section" style="display:none">
            <div class="pfe-section-hd">
                <span class="pfe-section-title"><?php esc_html_e('HTML del formulario', 'popup-form-engine'); ?></span>
            </div>
            <div class="pfe-section-bd">
                <textarea data-pfe-field="html_content" rows="10" class="large-text"
                          placeholder="<input type='text' name='nombre'>"></textarea>
                <p class="description"><?php esc_html_e('No incluyas etiquetas &lt;form&gt;. Serán rechazadas al guardar.', 'popup-form-engine'); ?></p>
            </div>
        </div>

        <!-- ── Bloque 2b: Campos (modo visual) ──────────────────────────── -->
        <div class="pfe-visual-mode-section pfe-section">
            <div class="pfe-section-hd">
                <span class="pfe-section-title"><?php esc_html_e('Campos', 'popup-form-engine'); ?></span>
            </div>
            <div class="pfe-section-bd">
                <div class="pfe-fields-list"></div>
                <button type="button" class="pfe-add-field button button-secondary" style="margin-top:.4rem">
                    <?php esc_html_e('+ Añadir campo', 'popup-form-engine'); ?>
                </button>
            </div>
        </div>

        <!-- ── Bloque 3: Newsletter ──────────────────────────────────────── -->
        <div class="pfe-section pfe-newsletter-section">
            <div class="pfe-section-hd">
                <label class="pfe-toggle-label">
                    <input type="checkbox" data-pfe-field="nl_enabled" value="1">
                    <span><?php esc_html_e('Newsletter', 'popup-form-engine'); ?></span>
                </label>
                <p class="pfe-section-desc"><?php esc_html_e('Muestra un checkbox de consentimiento de suscripción. Requiere activar la integración global en la pestaña Newsletter.', 'popup-form-engine'); ?></p>
            </div>
            <div class="pfe-nl-details pfe-section-bd" style="display:none">
                <table class="form-table pfe-compact-table" role="presentation">
                    <tr>
                        <th><?php esc_html_e('Posición', 'popup-form-engine'); ?></th>
                        <td>
                            <select data-pfe-field="nl_position">
                                <option value="before_submit"><?php esc_html_e('Antes del botón enviar', 'popup-form-engine'); ?></option>
                                <option value="after_submit"><?php esc_html_e('Después del botón enviar', 'popup-form-engine'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Etiqueta', 'popup-form-engine'); ?></th>
                        <td><input type="text" data-pfe-field="nl_label" class="large-text"
                                   placeholder="<?php esc_attr_e('Quiero recibir novedades por email', 'popup-form-engine'); ?>"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Pre-marcado', 'popup-form-engine'); ?></th>
                        <td><label><input type="checkbox" data-pfe-field="nl_prechecked" value="1"> <?php esc_html_e('Marcado por defecto', 'popup-form-engine'); ?></label></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Obligatorio', 'popup-form-engine'); ?></th>
                        <td><label><input type="checkbox" data-pfe-field="nl_required" value="1"> <?php esc_html_e('Requerir consentimiento para enviar', 'popup-form-engine'); ?></label></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- ── Bloque 4: Llámame ─────────────────────────────────────────── -->
        <div class="pfe-section pfe-callback-section">
            <div class="pfe-section-hd">
                <label class="pfe-toggle-label">
                    <input type="checkbox" data-pfe-field="callback_enabled" value="1">
                    <span><?php esc_html_e('Llámame', 'popup-form-engine'); ?></span>
                </label>
                <p class="pfe-section-desc"><?php esc_html_e('Añade un checkbox para que el usuario solicite una llamada y elija día y hora preferidos.', 'popup-form-engine'); ?></p>
            </div>
            <div class="pfe-callback-details pfe-section-bd" style="display:none">
                <table class="form-table pfe-compact-table" role="presentation">
                    <tr>
                        <th><?php esc_html_e('Etiqueta del checkbox', 'popup-form-engine'); ?></th>
                        <td><input type="text" data-pfe-field="callback_label" class="large-text"
                                   placeholder="<?php esc_attr_e('Quiero que me llaméis', 'popup-form-engine'); ?>"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Destinatarios (uno por línea)', 'popup-form-engine'); ?></th>
                        <td>
                            <textarea data-pfe-field="callback_email_recipients" rows="3" class="large-text"
                                      placeholder="correo@ejemplo.com"></textarea>
                            <p class="description"><?php esc_html_e('Cuando el usuario solicita llamada, se envía un email a estos destinatarios.', 'popup-form-engine'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

    </div><!-- /.pfe-form-block -->
</template>


<!-- ══ PDF FORMS ══════════════════════════════════════════════════════════════ -->
<hr style="margin: 1.5rem 0 1rem;">
<h2 style="font-size:1.1rem;margin-bottom:.75rem;">
    <?php esc_html_e('Formularios PDF (popups de enlaces .pdf)', 'popup-form-engine'); ?>
</h2>
<p class="description" style="margin-bottom:.75rem;">
    <?php esc_html_e('Configura el formulario que aparece al hacer clic en enlaces PDF. Usa data-pdf-form-slug="slug" en el enlace para seleccionar un formulario concreto; sin ese atributo se usa el slug "default".', 'popup-form-engine'); ?>
</p>

<!-- ── PDF list view ────────────────────────────────────────────────────────── -->
<div id="pfe-pdf-forms-list-view">

    <?php
    $pdfForms = $this->settings->getPdfForms();
    if (empty($pdfForms)): ?>
        <p class="pfe-no-forms-placeholder" id="pfe-pdf-no-forms-placeholder">
            <?php esc_html_e("No hay formularios PDF. Pulsa '+ Añadir formulario PDF' para crear el primero.", 'popup-form-engine'); ?>
        </p>
    <?php else: ?>
        <table class="widefat striped pfe-forms-index-table" id="pfe-pdf-forms-index-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Slug', 'popup-form-engine'); ?></th>
                    <th><?php esc_html_e('Título del popup', 'popup-form-engine'); ?></th>
                    <th><?php esc_html_e('Acciones', 'popup-form-engine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pdfForms as $idx => $pdfForm): ?>
                    <tr>
                        <td><code><?php echo esc_html($pdfForm['slug'] ?? ''); ?></code></td>
                        <td><?php echo esc_html($pdfForm['title'] ?? ''); ?></td>
                        <td class="pfe-form-row-actions">
                            <button type="button"
                                    class="pfe-pdf-edit-form button button-small"
                                    data-pdf-form-index="<?php echo esc_attr($idx); ?>">
                                <?php esc_html_e('Editar', 'popup-form-engine'); ?>
                            </button>
                            <button type="button"
                                    class="pfe-pdf-delete-form button button-small button-link-delete"
                                    data-pdf-form-index="<?php echo esc_attr($idx); ?>"
                                    data-pdf-form-slug="<?php echo esc_attr($pdfForm['slug'] ?? ''); ?>">
                                <?php esc_html_e('Eliminar', 'popup-form-engine'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <button type="button" id="pfe-pdf-add-form" class="button button-secondary pfe-add-form-btn">
        <?php esc_html_e('+ Añadir formulario PDF', 'popup-form-engine'); ?>
    </button>

</div><!-- /#pfe-pdf-forms-list-view -->

<!-- ── PDF edit view ─────────────────────────────────────────────────────────── -->
<div id="pfe-pdf-forms-edit-view" style="display:none" aria-live="polite">

    <div class="pfe-edit-breadcrumb">
        <button type="button" id="pfe-pdf-back-to-list" class="button button-link pfe-back-btn">
            &#8592; <?php esc_html_e('Volver al listado PDF', 'popup-form-engine'); ?>
        </button>
    </div>

    <div id="pfe-pdf-edit-card-slot"></div>

    <div class="pfe-edit-actions">
        <button type="button" id="pfe-pdf-save-form" class="button button-primary">
            <?php esc_html_e('Guardar formulario PDF', 'popup-form-engine'); ?>
        </button>
        <button type="button" id="pfe-pdf-cancel-edit" class="button button-secondary">
            <?php esc_html_e('Cancelar', 'popup-form-engine'); ?>
        </button>
    </div>

</div><!-- /#pfe-pdf-forms-edit-view -->

<!-- ── PDF forms JSON hidden input ───────────────────────────────────────────── -->
<input type="hidden" id="pfe-pdf-forms-json-input" name="pfe_pdf_forms_json" value="">

<!-- ── PDF form template ─────────────────────────────────────────────────────── -->
<template id="pfe-pdf-form-tpl">
    <div class="pfe-form-block">

        <!-- Identificación -->
        <div class="pfe-section">
            <div class="pfe-section-hd">
                <span class="pfe-section-title"><?php esc_html_e('Identificación', 'popup-form-engine'); ?></span>
            </div>
            <div class="pfe-section-bd">
                <table class="form-table pfe-compact-table" role="presentation">
                    <tr>
                        <th><label><?php esc_html_e('Slug', 'popup-form-engine'); ?> <span class="pfe-required" aria-hidden="true">*</span></label></th>
                        <td>
                            <input type="text" data-pfe-pdf-field="slug" class="regular-text" placeholder="default" required>
                            <p class="description"><?php esc_html_e('Usa "default" para todos los PDF sin slug específico.', 'popup-form-engine'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e('Título del popup', 'popup-form-engine'); ?></label></th>
                        <td><input type="text" data-pfe-pdf-field="title" class="regular-text" placeholder="<?php esc_attr_e('Recibe el PDF por email', 'popup-form-engine'); ?>"></td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e('Mensaje de éxito', 'popup-form-engine'); ?></label></th>
                        <td><textarea data-pfe-pdf-field="success_msg" rows="2" class="large-text" placeholder="<?php esc_attr_e('El enlace fue enviado a tu email.', 'popup-form-engine'); ?>"></textarea></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Campos -->
        <div class="pfe-section">
            <div class="pfe-section-hd">
                <span class="pfe-section-title"><?php esc_html_e('Campos', 'popup-form-engine'); ?></span>
            </div>
            <div class="pfe-section-bd">
                <div class="pfe-pdf-fields-list"></div>
                <button type="button" class="pfe-pdf-add-field button button-secondary" style="margin-top:.4rem">
                    <?php esc_html_e('+ Añadir campo', 'popup-form-engine'); ?>
                </button>
            </div>
        </div>

        <!-- Newsletter -->
        <div class="pfe-section pfe-newsletter-section">
            <div class="pfe-section-hd">
                <label class="pfe-toggle-label">
                    <input type="checkbox" data-pfe-pdf-field="nl_enabled" value="1">
                    <span><?php esc_html_e('Newsletter', 'popup-form-engine'); ?></span>
                </label>
                <p class="pfe-section-desc"><?php esc_html_e('Muestra checkbox de consentimiento de suscripción.', 'popup-form-engine'); ?></p>
            </div>
            <div class="pfe-pdf-nl-details pfe-section-bd" style="display:none">
                <table class="form-table pfe-compact-table" role="presentation">
                    <tr>
                        <th><?php esc_html_e('Etiqueta', 'popup-form-engine'); ?></th>
                        <td><input type="text" data-pfe-pdf-field="nl_label" class="large-text"
                                   placeholder="<?php esc_attr_e('Quiero recibir novedades por email', 'popup-form-engine'); ?>"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Pre-marcado', 'popup-form-engine'); ?></th>
                        <td><label><input type="checkbox" data-pfe-pdf-field="nl_prechecked" value="1">
                            <?php esc_html_e('Marcado por defecto', 'popup-form-engine'); ?></label></td>
                    </tr>
                </table>
            </div>
        </div>

    </div><!-- /.pfe-form-block -->
</template>


<!-- ══ TEMPLATE: field row ════════════════════════════════════════════════════ -->
<template id="pfe-field-tpl">
    <div class="pfe-field-row">
        <select data-pfe-ff="type" aria-label="<?php esc_attr_e('Tipo de campo', 'popup-form-engine'); ?>">
            <option value="text"><?php esc_html_e('Texto', 'popup-form-engine'); ?></option>
            <option value="email"><?php esc_html_e('Email', 'popup-form-engine'); ?></option>
            <option value="tel"><?php esc_html_e('Teléfono', 'popup-form-engine'); ?></option>
            <option value="textarea"><?php esc_html_e('Área de texto', 'popup-form-engine'); ?></option>
            <option value="select"><?php esc_html_e('Desplegable', 'popup-form-engine'); ?></option>
            <option value="checkbox"><?php esc_html_e('Checkbox', 'popup-form-engine'); ?></option>
        </select>
        <input type="text" data-pfe-ff="name"
               placeholder="<?php esc_attr_e('name (slug)', 'popup-form-engine'); ?>"
               aria-label="<?php esc_attr_e('Name del campo', 'popup-form-engine'); ?>">
        <input type="text" data-pfe-ff="label"
               placeholder="<?php esc_attr_e('Etiqueta', 'popup-form-engine'); ?>"
               aria-label="<?php esc_attr_e('Etiqueta visible', 'popup-form-engine'); ?>">
        <input type="text" data-pfe-ff="placeholder"
               placeholder="<?php esc_attr_e('Placeholder', 'popup-form-engine'); ?>"
               aria-label="<?php esc_attr_e('Placeholder', 'popup-form-engine'); ?>">
        <label class="pfe-required-label">
            <input type="checkbox" data-pfe-ff="required">
            <?php esc_html_e('Req.', 'popup-form-engine'); ?>
        </label>
        <button type="button" class="pfe-remove-field button button-link-delete"
                aria-label="<?php esc_attr_e('Eliminar campo', 'popup-form-engine'); ?>">
            &times;
        </button>
        <div class="pfe-field-options-wrap" style="display:none;width:100%;margin-top:4px;">
            <textarea data-pfe-ff="options_raw" rows="3" class="large-text"
                      placeholder="<?php esc_attr_e("madrid:Madrid\nbarcelona:Barcelona\nvalencia", 'popup-form-engine'); ?>"
                      aria-label="<?php esc_attr_e('Opciones del desplegable (valor:Etiqueta por línea)', 'popup-form-engine'); ?>"></textarea>
            <p class="description" style="margin:2px 0 0;"><?php esc_html_e('Una opción por línea. Formato: valor:Etiqueta (o solo Etiqueta si valor = etiqueta).', 'popup-form-engine'); ?></p>
        </div>
    </div>
</template>
