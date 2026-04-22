<?php
defined('ABSPATH') || exit;

$logger = new \PopupFormEngine\Logger();

// ── Filters from GET ────────────────────────────────────────────────────────────
$filters = [
    'flow_type'      => sanitize_key($_GET['log_flow']    ?? ''),
    'consent_status' => sanitize_key($_GET['log_consent'] ?? ''),
    'date_from'      => sanitize_text_field($_GET['log_from'] ?? ''),
    'date_to'        => sanitize_text_field($_GET['log_to']   ?? ''),
];
$activeFilters = array_filter($filters, fn($v) => $v !== '');

$perPage = 25;
$page    = max(1, (int) ($_GET['log_page'] ?? 1));
$result  = $logger->getLogs($filters, $page, $perPage);
$rows    = $result['rows'];
$total   = $result['total'];
$pages   = (int) ceil($total / $perPage);

// Base URL for pagination (preserves active filters).
function pfe_logs_url(array $extra = []): string {
    $base = ['page' => 'popup-form-engine', 'tab' => 'logs'];
    return esc_url(add_query_arg(array_merge($base, $extra), admin_url('admin.php')));
}
?>

<?php if (!empty($_GET['cleaned'])): ?>
<div class="notice notice-success is-dismissible">
    <p><?php printf(
        esc_html__('%d registro(s) eliminado(s).', 'popup-form-engine'),
        (int) $_GET['cleaned']
    ); ?></p>
</div>
<?php endif; ?>

<h2>
    <?php esc_html_e('Registros', 'popup-form-engine'); ?>
    <span class="pfe-log-count">(<?php echo esc_html(number_format_i18n($total)); ?>)</span>
</h2>

<!-- ── Filters ──────────────────────────────────────────────────────────────── -->
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

    <input type="date" name="log_from" value="<?php echo esc_attr($filters['date_from']); ?>" aria-label="<?php esc_attr_e('Desde', 'popup-form-engine'); ?>">
    <input type="date" name="log_to"   value="<?php echo esc_attr($filters['date_to']);   ?>" aria-label="<?php esc_attr_e('Hasta', 'popup-form-engine'); ?>">

    <button type="submit" class="button"><?php esc_html_e('Filtrar', 'popup-form-engine'); ?></button>

    <?php if ($activeFilters): ?>
        <a href="<?php echo pfe_logs_url(); ?>" class="button">
            <?php esc_html_e('Limpiar filtros', 'popup-form-engine'); ?>
        </a>
    <?php endif; ?>
</form>

<!-- ── CSV export ───────────────────────────────────────────────────────────── -->
<?php
$csvArgs = array_merge(
    ['action' => 'pfe_export_csv'],
    array_filter($filters, fn($v) => $v !== '')
);
$csvUrl  = wp_nonce_url(
    add_query_arg($csvArgs, admin_url('admin-post.php')),
    'pfe_export_csv'
);
?>
<a href="<?php echo esc_url($csvUrl); ?>" class="button" style="margin-bottom:1rem;">
    &#8659; <?php esc_html_e('Exportar CSV', 'popup-form-engine'); ?>
</a>

<!-- ── Rows ─────────────────────────────────────────────────────────────────── -->
<?php if (empty($rows)): ?>
    <p><?php esc_html_e('Sin registros todavía.', 'popup-form-engine'); ?></p>
<?php else: ?>

<table class="widefat striped pfe-logs-table">
    <thead>
        <tr>
            <th><?php esc_html_e('Fecha',       'popup-form-engine'); ?></th>
            <th><?php esc_html_e('IP',           'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Tipo',         'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Formulario',   'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Email',        'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Consent',      'popup-form-engine'); ?></th>
            <th><?php esc_html_e('NL enviado',   'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Email interno','popup-form-engine'); ?></th>
            <th><?php esc_html_e('Estado',       'popup-form-engine'); ?></th>
            <th><?php esc_html_e('Payload',      'popup-form-engine'); ?></th>
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
                        <?php
                        $nlResp = $row['newsletter_response'] ?? '';
                        if ($nlResp !== '' && $nlResp !== 'no_aplica'):
                        ?>
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
                    <?php if (!empty($row['payload_json'])): ?>
                        <details>
                            <summary style="cursor:pointer"><?php esc_html_e('Ver', 'popup-form-engine'); ?></summary>
                            <pre style="max-width:280px;overflow:auto;font-size:.72rem;background:#f7f7f7;padding:.5rem;margin:.25rem 0;"><?php
                                $payload = json_decode($row['payload_json'], true);
                                if (is_array($payload)) {
                                    foreach ($payload as $k => $v) {
                                        echo esc_html($k) . ': ' . esc_html((string) $v) . "\n";
                                    }
                                } else {
                                    echo esc_html($row['payload_json']);
                                }
                            ?></pre>
                        </details>
                    <?php else: ?>
                        &mdash;
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- ── Pagination ───────────────────────────────────────────────────────────── -->
<?php if ($pages > 1): ?>
<div class="tablenav" style="margin-top:.5rem;">
    <div class="tablenav-pages">
        <?php
        $filterArgs = array_filter($filters, fn($v) => $v !== '');
        for ($i = 1; $i <= $pages; $i++):
        ?>
            <a href="<?php echo pfe_logs_url(array_merge($filterArgs, ['log_page' => $i])); ?>"
               class="button<?php echo $i === $page ? ' button-primary' : ''; ?>">
                <?php echo esc_html($i); ?>
            </a>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; // end rows ?>

<!-- ── Log cleanup ───────────────────────────────────────────────────────────── -->
<hr style="margin-top:2rem;">
<h3><?php esc_html_e('Limpiar registros antiguos', 'popup-form-engine'); ?></h3>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('pfe_clean_logs'); ?>
    <input type="hidden" name="action" value="pfe_clean_logs">
    <label>
        <?php esc_html_e('Eliminar registros de más de', 'popup-form-engine'); ?>
        <input type="number" name="clean_days" value="30" min="1" max="3650" class="small-text">
        <?php esc_html_e('días', 'popup-form-engine'); ?>
    </label>
    <button type="submit" class="button button-link-delete"
            onclick="return confirm('<?php esc_attr_e('¿Eliminar los registros anteriores a la fecha indicada?', 'popup-form-engine'); ?>');">
        <?php esc_html_e('Limpiar', 'popup-form-engine'); ?>
    </button>
</form>
