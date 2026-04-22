<?php defined('ABSPATH') || exit;
$general = $this->settings->getGeneral();
?>
<p class="description" style="margin:.5rem 0 1.25rem">
    <?php esc_html_e('El nombre y email del remitente se usan como cabecera "From:" en todos los emails que envía el plugin (notificaciones de Llámame, envío de PDFs, etc.).', 'popup-form-engine'); ?>
</p>
<table class="form-table" role="presentation">
    <tr>
        <th scope="row"><label for="from_name"><?php esc_html_e('Nombre remitente', 'popup-form-engine'); ?></label></th>
        <td><input type="text" id="from_name" name="from_name" class="regular-text"
                   value="<?php echo esc_attr($general['from_name'] ?? get_bloginfo('name')); ?>"></td>
    </tr>
    <tr>
        <th scope="row"><label for="from_email"><?php esc_html_e('Email remitente', 'popup-form-engine'); ?></label></th>
        <td><input type="email" id="from_email" name="from_email" class="regular-text"
                   value="<?php echo esc_attr($general['from_email'] ?? get_option('admin_email')); ?>"></td>
    </tr>
    <tr>
        <th scope="row"><label for="rate_limit"><?php esc_html_e('Rate limit (envíos/hora/IP)', 'popup-form-engine'); ?></label></th>
        <td>
            <input type="number" id="rate_limit" name="rate_limit" class="small-text" min="1" max="1000"
                   value="<?php echo esc_attr($general['rate_limit'] ?? 5); ?>">
            <p class="description"><?php esc_html_e('Máximo de envíos de formulario permitidos por IP en una hora.', 'popup-form-engine'); ?></p>
        </td>
    </tr>
</table>
