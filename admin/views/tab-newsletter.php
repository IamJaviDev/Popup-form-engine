<?php
defined('ABSPATH') || exit;
$nl = $this->settings->getNewsletter();

// Resolve the current host from saved options (supports all legacy formats).
$savedHost = trim((string) ($nl['host'] ?? ''));
if ($savedHost === '' && !empty($nl['endpoint'])) {
    // Legacy: full URL stored — extract host for display.
    $savedHost = (string) parse_url($nl['endpoint'], PHP_URL_HOST);
}

$previewEndpoint = $savedHost !== ''
    ? 'https://' . $savedHost . '/newsletter/subscribe'
    : 'https://(host)/newsletter/subscribe';
?>
<table class="form-table" role="presentation">
    <tr>
        <th scope="row"><?php esc_html_e('Activar newsletter', 'popup-form-engine'); ?></th>
        <td>
            <label>
                <input type="checkbox" name="newsletter_enabled" value="1"<?php checked(!empty($nl['enabled'])); ?>>
                <?php esc_html_e('Enviar suscriptores al endpoint configurado', 'popup-form-engine'); ?>
            </label>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="newsletter_host"><?php esc_html_e('Host receptor', 'popup-form-engine'); ?></label></th>
        <td>
            <input type="text" id="newsletter_host" name="newsletter_host" class="regular-text"
                   value="<?php echo esc_attr($savedHost); ?>"
                   placeholder="nl.netsrvc.net">
            <p class="description">
                <?php esc_html_e('Solo el dominio del servidor de newsletter. Sin protocolo ni path.', 'popup-form-engine'); ?>
                <?php esc_html_e('Ej: nl.netsrvc.net', 'popup-form-engine'); ?>
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="newsletter_timeout"><?php esc_html_e('Timeout (segundos)', 'popup-form-engine'); ?></label></th>
        <td>
            <input type="number" id="newsletter_timeout" name="newsletter_timeout" class="small-text"
                   min="1" max="60" value="<?php echo esc_attr($nl['timeout'] ?? 10); ?>">
        </td>
    </tr>
    <tr>
        <th scope="row"><?php esc_html_e('Endpoint resultante', 'popup-form-engine'); ?></th>
        <td>
            <input type="text" id="pfe-nl-preview" class="large-text" readonly
                   value="<?php echo esc_attr($previewEndpoint); ?>"
                   style="background:#f6f7f7;color:#555;">
            <p class="description">
                <?php esc_html_e('Protocolo (https) y path (/newsletter/subscribe) son fijos.', 'popup-form-engine'); ?>
                <br><?php esc_html_e('source_domain en el payload será siempre el dominio de esta web.', 'popup-form-engine'); ?>
            </p>
        </td>
    </tr>
</table>

<script>
(function () {
    'use strict';
    var hostEl  = document.getElementById('newsletter_host');
    var preview = document.getElementById('pfe-nl-preview');
    if (!hostEl || !preview) return;

    function updatePreview() {
        var h = (hostEl.value || '').trim();
        preview.value = h ? 'https://' + h + '/newsletter/subscribe' : 'https://(host)/newsletter/subscribe';
    }

    hostEl.addEventListener('input', updatePreview);
})();
</script>
