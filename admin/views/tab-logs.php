<?php
defined('ABSPATH') || exit;

$logger = new \PopupFormEngine\Logger();

// ── Stats ─────────────────────────────────────────────────────────────────────
$stats = $logger->getStats();

// ── Filters from GET ──────────────────────────────────────────────────────────
$filters = [
    'flow_type'      => sanitize_key($_GET['log_flow']      ?? ''),
    'consent_status' => sanitize_key($_GET['log_consent']   ?? ''),
    'date_from'      => sanitize_text_field($_GET['log_from']     ?? ''),
    'date_to'        => sanitize_text_field($_GET['log_to']       ?? ''),
    'search_email'   => sanitize_text_field($_GET['search_email'] ?? ''),
];
$activeFilters = array_filter($filters, fn($v) => $v !== '');

$perPage = 50;
$page    = max(1, (int) ($_GET['log_page'] ?? 1));
$result  = $logger->getLogs($filters, $page, $perPage);
$rows    = $result['rows'];
$total   = $result['total'];
$pages   = (int) ceil($total / $perPage);

// ── Retention settings ────────────────────────────────────────────────────────
$settings  = new \PopupFormEngine\Settings();
$retention = $settings->getLogsRetention();

// ── Helpers ───────────────────────────────────────────────────────────────────
if (!function_exists('pfe_logs_url')) {
    function pfe_logs_url(array $extra = []): string {
        $base = ['page' => 'popup-form-engine', 'tab' => 'logs'];
        return esc_url(add_query_arg(array_merge($base, $extra), admin_url('admin.php')));
    }
}
if (!function_exists('pfe_format_payload_pretty')) {
    function pfe_format_payload_pretty(string $json): string {
        $decoded = json_decode($json, true);
        return is_array($decoded)
            ? (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : $json;
    }
}
?>

<style>
.pfe-stat-card { flex:1; padding:1rem; background:#fff; border:1px solid #c3c4c7; border-radius:4px; text-align:center; }
.pfe-stat-card strong { display:block; font-size:1.6rem; color:#2271b1; }
.pfe-stat-card span { font-size:.85rem; color:#646970; }
.pfe-logs-count-line { margin:.5rem 0; color:#646970; font-size:.9rem; }
</style>

<?php if (!empty($_GET['saved'])): ?>
<div class="notice notice-success is-dismissible">
    <p><?php esc_html_e('Configuración de mantenimiento guardada.', 'popup-form-engine'); ?></p>
</div>
<?php endif; ?>

<?php if (!empty($_GET['cleaned'])): ?>
<div class="notice notice-success is-dismissible">
    <p><?php printf(
        esc_html__('%d registro(s) eliminado(s).', 'popup-form-engine'),
        (int) $_GET['cleaned']
    ); ?></p>
</div>
<?php endif; ?>

<!-- ── Stats cards ────────────────────────────────────────────────────────── -->
<div style="display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;">
    <div class="pfe-stat-card">
        <strong><?php echo esc_html(number_format_i18n($stats['total'])); ?></strong>
        <span><?php esc_html_e('Total', 'popup-form-engine'); ?></span>
    </div>
    <div class="pfe-stat-card">
        <strong><?php echo esc_html(number_format_i18n($stats['today'])); ?></strong>
        <span><?php esc_html_e('Hoy', 'popup-form-engine'); ?></span>
    </div>
    <div class="pfe-stat-card">
        <strong><?php echo esc_html(number_format_i18n($stats['week'])); ?></strong>
        <span><?php esc_html_e('Última semana', 'popup-form-engine'); ?></span>
    </div>
    <div class="pfe-stat-card">
        <strong><?php echo esc_html(number_format_i18n($stats['month'])); ?></strong>
        <span><?php esc_html_e('Último mes', 'popup-form-engine'); ?></span>
    </div>
</div>

<h2><?php esc_html_e('Registros', 'popup-form-engine'); ?></h2>

<!-- ── Filters ───────────────────────────────────────────────────────────── -->
<form method="get" action="" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
    <input type="hidden" name="page" value="popup-form-engine">
    <input type="hidden" name="tab"  value="logs">

    <select name="log_flow">
        <option value=""><?php esc_html_e('Todos los tipos', 'popup-form-engine'); ?></option>
        <?php foreach (['pdf', 'generic', 'cf7', 'honeypot', 'invalid'] as $ft): ?>
            <option value="<?php echo esc_attr($ft); ?>"<?php selected($filters['flow_type'], $ft); ?>>
                <?php echo esc_html($ft); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="log_consent">
        <option value=""><?php esc_html_e('Cualquier consent', 'popup-form-engine'); ?></option>
        <?php foreach (['no_aplica', 'true', 'false'] as $cs): ?>
            <option value="<?php echo esc_attr($cs); ?>"<?php selected($filters['consent_status'], $cs); ?>>
                <?php echo esc_html($cs); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <input type="date" name="log_from" value="<?php echo esc_attr($filters['date_from']); ?>"
           aria-label="<?php esc_attr_e('Desde', 'popup-form-engine'); ?>">
    <input type="date" name="log_to"   value="<?php echo esc_attr($filters['date_to']); ?>"
           aria-label="<?php esc_attr_e('Hasta', 'popup-form-engine'); ?>">

    <input type="search" name="search_email"
           value="<?php echo esc_attr($filters['search_email']); ?>"
           placeholder="<?php esc_attr_e('Buscar email…', 'popup-form-engine'); ?>"
           style="width:200px;">

    <button type="submit" class="button"><?php esc_html_e('Filtrar', 'popup-form-engine'); ?></button>

    <?php if ($activeFilters): ?>
        <a href="<?php echo pfe_logs_url(); ?>" class="button">
            <?php esc_html_e('Limpiar filtros', 'popup-form-engine'); ?>
        </a>
    <?php endif; ?>
</form>

<!-- ── CSV export ────────────────────────────────────────────────────────── -->
<?php
$csvArgs = array_merge(
    ['action' => 'pfe_export_csv'],
    array_filter([
        'flow_type'      => $filters['flow_type'],
        'consent_status' => $filters['consent_status'],
        'date_from'      => $filters['date_from'],
        'date_to'        => $filters['date_to'],
        'search_email'   => $filters['search_email'],
    ], fn($v) => $v !== '')
);
$csvUrl = wp_nonce_url(
    add_query_arg($csvArgs, admin_url('admin-post.php')),
    'pfe_export_csv'
);
?>
<a href="<?php echo esc_url($csvUrl); ?>" class="button" style="margin-bottom:1rem;">
    &#8659; <?php esc_html_e('Exportar CSV', 'popup-form-engine'); ?>
</a>

<!-- ── Rows ──────────────────────────────────────────────────────────────── -->
<?php if (empty($rows)): ?>
    <p><?php esc_html_e('Sin registros todavía.', 'popup-form-engine'); ?></p>
<?php else: ?>

<?php
$from = ($page - 1) * $perPage + 1;
$to   = min($page * $perPage, $total);
?>
<p class="pfe-logs-count-line">
    <?php printf(
        esc_html__('Mostrando %1$d&ndash;%2$d de %3$d registros', 'popup-form-engine'),
        $from, $to, $total
    ); ?>
</p>

<table class="widefat striped pfe-logs-table">
    <thead>
        <tr>
            <th><?php esc_html_e('Fecha',        'popup-form-engine'); ?></th>
            <th><?php esc_html_e('IP',            'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Tipo',          'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Formulario',    'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Email',         'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Consent',       'popup-form-engine'); ?></th>
            <th><?php esc_html_e('NL enviado',    'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Email interno', 'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Estado',        'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Detalle',       'popup-form-engine'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td style="white-space:nowrap"><?php echo esc_html($row['created_at']); ?></td>
                <td><code><?php echo esc_html($row['ip']); ?></code></td>
                <td><span class="pfe-flow-badge pfe-flow-<?php echo esc_attr($row['flow_type']); ?>"><?php echo esc_html($row['flow_type']); ?></span></td>
                <td><?php echo esc_html($row['form_identifier'] ?? '—'); ?></td>
                <td><?php echo esc_html($row['email'] ?? ''); ?></td>
                <td><?php echo esc_html($row['consent_status']); ?></td>
                <td>
                    <?php if ($row['newsletter_sent']): ?>
                        <span style="color:green">&#10003;</span>
                    <?php else: ?>
                        <span>—</span>
                        <?php $nlResp = $row['newsletter_response'] ?? ''; ?>
                        <?php if ($nlResp !== '' && $nlResp !== 'no_aplica'): ?>
                            <br><small style="color:#b00;font-size:.72rem;word-break:break-all"><?php echo esc_html($nlResp); ?></small>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td><?php echo $row['email_sent'] ? '<span style="color:green">&#10003;</span>' : '—'; ?></td>
                <td>
                    <span class="pfe-status-<?php echo esc_attr($row['status']); ?>">
                        <?php echo esc_html($row['status']); ?>
                    </span>
                    <?php if (!empty($row['error_message'])): ?>
                        <br><small style="color:#b00"><?php echo esc_html($row['error_message']); ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <button type="button" class="button button-small pfe-log-detail-btn"
                            data-id="<?php echo esc_attr((string) $row['id']); ?>">
                        <?php esc_html_e('Ver detalle', 'popup-form-engine'); ?>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- ── Pagination ────────────────────────────────────────────────────────── -->
<?php if ($pages > 1): ?>
<div class="tablenav" style="margin-top:.5rem;">
    <div class="tablenav-pages">
        <?php
        $base_url = add_query_arg(
            array_filter([
                'page'         => 'popup-form-engine',
                'tab'          => 'logs',
                'log_flow'     => $filters['flow_type'],
                'log_consent'  => $filters['consent_status'],
                'log_from'     => $filters['date_from'],
                'log_to'       => $filters['date_to'],
                'search_email' => $filters['search_email'],
            ], fn($v) => $v !== ''),
            admin_url('admin.php')
        );
        echo paginate_links([
            'base'      => esc_url_raw($base_url) . '&log_page=%#%',
            'format'    => '',
            'current'   => $page,
            'total'     => $pages,
            'prev_text' => __('&laquo; Anterior', 'popup-form-engine'),
            'next_text' => __('Siguiente &raquo;', 'popup-form-engine'),
        ]);
        ?>
    </div>
</div>
<?php endif; ?>

<?php endif; // end rows ?>

<!-- ── Template elements (modal content, one per row) ────────────────────── -->
<div id="pfe-log-templates" hidden>
<?php foreach ($rows as $row): ?>
    <template id="pfe-log-tpl-<?php echo esc_attr((string) $row['id']); ?>">
        <h3 style="margin-top:0"><?php printf(
            esc_html__('Detalle del log #%d', 'popup-form-engine'),
            (int) $row['id']
        ); ?></h3>
        <table class="widefat striped" style="margin-bottom:1rem;">
            <tbody>
                <tr><th style="width:35%"><?php esc_html_e('Fecha',               'popup-form-engine'); ?></th><td><?php echo esc_html($row['created_at']); ?></td></tr>
                <tr><th><?php esc_html_e('IP',                   'popup-form-engine'); ?></th><td><code><?php echo esc_html($row['ip']); ?></code></td></tr>
                <tr><th><?php esc_html_e('User Agent',           'popup-form-engine'); ?></th><td style="word-break:break-all;font-size:.8rem"><?php echo esc_html($row['user_agent'] ?? ''); ?></td></tr>
                <tr><th><?php esc_html_e('Tipo de flujo',        'popup-form-engine'); ?></th><td><?php echo esc_html($row['flow_type']); ?></td></tr>
                <tr><th><?php esc_html_e('Formulario',           'popup-form-engine'); ?></th><td><?php echo esc_html($row['form_identifier'] ?? '—'); ?></td></tr>
                <tr><th><?php esc_html_e('Email',                'popup-form-engine'); ?></th><td><?php echo esc_html($row['email'] ?? ''); ?></td></tr>
                <tr><th><?php esc_html_e('Consent',              'popup-form-engine'); ?></th><td><?php echo esc_html($row['consent_status']); ?></td></tr>
                <tr><th><?php esc_html_e('Newsletter enviado',   'popup-form-engine'); ?></th><td><?php echo $row['newsletter_sent'] ? esc_html__('Sí', 'popup-form-engine') : esc_html__('No', 'popup-form-engine'); ?></td></tr>
                <tr><th><?php esc_html_e('Respuesta backend NL', 'popup-form-engine'); ?></th><td><pre style="margin:0;font-size:.8rem;white-space:pre-wrap"><?php echo esc_html($row['newsletter_response'] ?? ''); ?></pre></td></tr>
                <tr><th><?php esc_html_e('Email interno',        'popup-form-engine'); ?></th><td><?php echo $row['email_sent'] ? esc_html__('Sí', 'popup-form-engine') : esc_html__('No', 'popup-form-engine'); ?></td></tr>
                <tr><th><?php esc_html_e('Estado',               'popup-form-engine'); ?></th><td><?php echo esc_html($row['status']); ?></td></tr>
                <tr><th><?php esc_html_e('Error',                'popup-form-engine'); ?></th><td><?php echo esc_html($row['error_message'] ?? ''); ?></td></tr>
                <tr>
                    <th><?php esc_html_e('Payload', 'popup-form-engine'); ?></th>
                    <td><pre class="pfe-log-payload-pre" style="margin:0;font-size:.8rem;white-space:pre-wrap;max-height:300px;overflow:auto;background:#f7f7f7;padding:.5rem"><?php
                        echo !empty($row['payload_json'])
                            ? esc_html(pfe_format_payload_pretty($row['payload_json']))
                            : '—';
                    ?></pre></td>
                </tr>
            </tbody>
        </table>
        <?php if (!empty($row['payload_json'])): ?>
        <button type="button" class="button button-primary pfe-log-copy-btn">
            <?php esc_html_e('Copiar payload al portapapeles', 'popup-form-engine'); ?>
        </button>
        <?php endif; ?>
    </template>
<?php endforeach; ?>
</div>

<!-- ── Modal overlay (populated by JS) ───────────────────────────────────── -->
<div id="pfe-log-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99999;align-items:center;justify-content:center;padding:1rem;">
    <div id="pfe-log-modal" style="background:#fff;border-radius:4px;max-width:780px;width:100%;max-height:90vh;overflow:auto;padding:1.5rem;position:relative;">
        <button id="pfe-log-modal-close" type="button"
                style="position:absolute;top:.75rem;right:.75rem;cursor:pointer;background:none;border:none;font-size:1.5rem;line-height:1;color:#3c434a;"
                aria-label="<?php esc_attr_e('Cerrar', 'popup-form-engine'); ?>">&times;</button>
        <div id="pfe-log-modal-body"></div>
    </div>
</div>

<hr style="margin-top:2rem;">

<!-- ── Maintenance section (inside WP main form, saved via handleSave 'logs') -->
<h3><?php esc_html_e('Mantenimiento de logs', 'popup-form-engine'); ?></h3>
<table class="form-table">
    <tr>
        <th scope="row"><?php esc_html_e('Limpieza automática', 'popup-form-engine'); ?></th>
        <td>
            <label>
                <input type="checkbox" name="logs_cleanup_enabled" value="1"
                       <?php checked(!empty($retention['enabled'])); ?>>
                <?php esc_html_e('Activar limpieza automática diaria', 'popup-form-engine'); ?>
            </label>
        </td>
    </tr>
    <tr>
        <th scope="row"><?php esc_html_e('Borrar logs con más de', 'popup-form-engine'); ?></th>
        <td>
            <input type="number" name="logs_cleanup_days"
                   value="<?php echo esc_attr((string) $retention['days']); ?>"
                   id="pfe-cleanup-days" min="1" max="365" class="small-text">
            <?php esc_html_e('días', 'popup-form-engine'); ?>
            <p class="description">
                <?php esc_html_e('Recomendado: 90 días. Solo se aplica si la limpieza automática está activada.', 'popup-form-engine'); ?>
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row"><?php esc_html_e('Limpieza manual', 'popup-form-engine'); ?></th>
        <td>
            <button type="button" class="button pfe-logs-purge-btn">
                <?php esc_html_e('Borrar logs antiguos ahora', 'popup-form-engine'); ?>
            </button>
            <span class="pfe-logs-purge-status" style="margin-left:.5rem;"></span>
            <p class="description">
                <?php esc_html_e('Borra todos los logs con más antigüedad que el valor configurado arriba.', 'popup-form-engine'); ?>
            </p>
        </td>
    </tr>
</table>
