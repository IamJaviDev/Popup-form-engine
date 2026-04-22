<?php
defined('ABSPATH') || exit;

$pdfNl    = $this->settings->getPdfNewsletter();
$mappings = $this->settings->getPdfTemplates();
$templDir = PFE_DIR . 'templates/';

// Slugs marked [new] in the installer (had no legacy PHP mapping).
$legacyNew = ['baja-de-un-vehiculo-robado'];
// All slugs from the installer that originated in Tota-form-detecterv2.
$legacySlugs = [
    'altas-y-rehabilitaciones-de-vehiculos',
    'certificado-de-destruccion',
    'impuesto-de-circulacion',
    'baja-de-vehiculos-por-exportacion',
    'cambio-de-titularidad-de-un-vehiculo',
    'cambio-de-domicilio',
    'baja-temporal-de-vehiculos',
    'multas-trafico',
    'notificaciones-de-sanciones-por-sms-o-email-dev',
    'duplicados-y-renovaciones',
    'informes-de-vehiculos-en-trafico',
    'tresta-consultar-multas-de-trafico-en-internet',
    'baja-de-un-vehiculo-robado',
];

function pfe_mapping_origin(string $needle, array $legacySlugs, array $legacyNew): string {
    if (in_array($needle, $legacyNew, true))    return '[new]';
    if (in_array($needle, $legacySlugs, true))  return '[legacy]';
    return '[custom]';
}
?>

<!-- ── Mode notice ─────────────────────────────────────────────────────────── -->
<h2><?php esc_html_e('Modo de resolución de plantilla PDF', 'popup-form-engine'); ?></h2>
<div class="notice notice-info inline" style="margin:0 0 16px 0;">
    <p>
        <strong><?php esc_html_e('Modo activo: PAGE_SLUG_TEMPLATE (legado)', 'popup-form-engine'); ?></strong><br>
        <?php esc_html_e(
            'La plantilla de email se selecciona según el slug de la página donde el usuario hizo clic en el PDF, '
            . 'no según el PDF clicado. Este comportamiento reproduce exactamente el plugin Tota-form-detecterv2.',
            'popup-form-engine'
        ); ?>
    </p>
    <p><?php esc_html_e(
        'Las plantillas de origen legado pueden contener enlaces PDF hardcodeados, imágenes de branding de RO-DES '
        . 'y URLs absolutas. No son multisite-friendly en su estado actual.',
        'popup-form-engine'
    ); ?></p>
</div>

<!-- ── Mappings CRUD ───────────────────────────────────────────────────────── -->
<h2><?php esc_html_e('Mappings slug → plantilla', 'popup-form-engine'); ?></h2>
<p class="description"><?php esc_html_e(
    'Primer match gana. El fallback cuando ningún mapping coincide es plantilla-base.html.',
    'popup-form-engine'
); ?></p>

<table class="widefat striped" id="pfe-pdf-mappings-table">
    <thead>
        <tr>
            <th><?php esc_html_e('slug_contains (fragmento del slug de página)', 'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Plantilla (nombre de archivo en /templates/)', 'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Archivo existe', 'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Origen', 'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Acción', 'popup-form-engine'); ?></th>
        </tr>
    </thead>
    <tbody id="pfe-pdf-mappings-rows">
        <?php foreach ($mappings as $i => $entry):
            $needle   = $entry['slug_contains']  ?? '';
            $tplFile  = $entry['template_file']  ?? '';
            $fullPath = $templDir . basename($tplFile);
            $exists   = file_exists($fullPath);
            $origin   = pfe_mapping_origin($needle, $legacySlugs, $legacyNew);
        ?>
        <tr class="pfe-pdf-mapping-row">
            <td>
                <input type="text" name="pdf_mappings[<?php echo $i; ?>][slug_contains]"
                       value="<?php echo esc_attr($needle); ?>" class="regular-text"
                       placeholder="ej: altas-y-rehabilitaciones">
            </td>
            <td>
                <input type="text" name="pdf_mappings[<?php echo $i; ?>][template_file]"
                       value="<?php echo esc_attr($tplFile); ?>" class="regular-text"
                       placeholder="ej: alta-y-rehabilitacion.html">
            </td>
            <td>
                <?php if ($exists): ?>
                    <span style="color:green">&#10003; <?php esc_html_e('Sí', 'popup-form-engine'); ?></span>
                <?php else: ?>
                    <span style="color:red">&#10007; <?php esc_html_e('No encontrado', 'popup-form-engine'); ?></span>
                <?php endif; ?>
            </td>
            <td>
                <span title="<?php echo esc_attr($origin === '[legacy]'
                    ? __('Mapping procedente de Tota-form-detecterv2.', 'popup-form-engine')
                    : ($origin === '[new]'
                        ? __('Mapping añadido en migración; plantilla existía sin mapping PHP.', 'popup-form-engine')
                        : __('Mapping añadido manualmente.', 'popup-form-engine')
                    )); ?>"><?php echo esc_html($origin); ?></span>
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

<!-- ── Newsletter en flujo PDF ─────────────────────────────────────────────── -->
<hr>
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

<p class="submit">
    <button type="submit" class="button button-primary"><?php esc_html_e('Guardar cambios', 'popup-form-engine'); ?></button>
</p>

<!-- ── Files on disk ───────────────────────────────────────────────────────── -->
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
            <th><?php esc_html_e('Referenciado en mappings', 'popup-form-engine'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($onDisk as $tpl): ?>
        <tr>
            <td><code><?php echo esc_html(basename($tpl)); ?></code></td>
            <td>
                <?php if (in_array(basename($tpl), $referenced, true)): ?>
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

<!-- Template row for JS add -->
<?php $tplIdx = 'TPL_IDX'; ?>
<template id="pfe-pdf-row-tpl">
    <tr class="pfe-pdf-mapping-row">
        <td>
            <input type="text" name="pdf_mappings[<?php echo $tplIdx; ?>][slug_contains]"
                   value="" class="regular-text" placeholder="ej: cambio-de-domicilio">
        </td>
        <td>
            <input type="text" name="pdf_mappings[<?php echo $tplIdx; ?>][template_file]"
                   value="" class="regular-text" placeholder="ej: cambio-domicilio.html">
        </td>
        <td><?php esc_html_e('—', 'popup-form-engine'); ?></td>
        <td>[custom]</td>
        <td><button type="button" class="button button-small pfe-pdf-remove-row"><?php esc_html_e('Eliminar', 'popup-form-engine'); ?></button></td>
    </tr>
</template>

<script>
(function () {
    var tbody  = document.getElementById('pfe-pdf-mappings-rows');
    var addBtn = document.getElementById('pfe-pdf-add-row');
    var tpl    = document.getElementById('pfe-pdf-row-tpl');
    if (!tbody || !addBtn || !tpl) return;

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
        var btn = e.target.closest('.pfe-pdf-remove-row');
        if (btn) {
            var row = btn.closest('.pfe-pdf-mapping-row');
            if (row) row.remove();
        }
    });
})();
</script>
