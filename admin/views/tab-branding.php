<?php
defined('ABSPATH') || exit;

$branding = $this->settings->getBranding();
?>
<h2><?php esc_html_e('Identidad de marca', 'popup-form-engine'); ?></h2>
<p class="description"><?php esc_html_e('Los valores configurados aquí están disponibles como placeholders en los templates de email PDF. Todos los campos son opcionales.', 'popup-form-engine'); ?></p>

<table class="form-table" role="presentation">
    <tr>
        <th scope="row">
            <label for="branding_empresa"><?php esc_html_e('Nombre de empresa', 'popup-form-engine'); ?></label>
        </th>
        <td>
            <input type="text" id="branding_empresa" name="branding_empresa" class="regular-text"
                   value="<?php echo esc_attr($branding['empresa']); ?>"
                   placeholder="<?php esc_attr_e('Mi Empresa S.L.', 'popup-form-engine'); ?>">
            <p class="description"><code>{{ empresa }}</code></p>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="branding_logo"><?php esc_html_e('Logo (URL)', 'popup-form-engine'); ?></label>
        </th>
        <td>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <input type="url" id="branding_logo" name="branding_logo" class="regular-text"
                       value="<?php echo esc_attr($branding['logo']); ?>"
                       placeholder="https://example.com/logo.png">
                <button type="button" class="button pfe-media-upload-btn" data-target="branding_logo">
                    <?php esc_html_e('Elegir imagen', 'popup-form-engine'); ?>
                </button>
            </div>
            <p class="description"><code>{{ logo }}</code></p>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="branding_web"><?php esc_html_e('URL de la web', 'popup-form-engine'); ?></label>
        </th>
        <td>
            <input type="url" id="branding_web" name="branding_web" class="regular-text"
                   value="<?php echo esc_attr($branding['web']); ?>"
                   placeholder="https://www.miempresa.com">
            <p class="description"><code>{{ web }}</code></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><?php esc_html_e('Colores', 'popup-form-engine'); ?></th>
        <td>
            <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:center">
                <label style="display:flex;flex-direction:column;gap:4px;font-weight:normal">
                    <?php esc_html_e('Color primario', 'popup-form-engine'); ?>
                    <div style="display:flex;align-items:center;gap:6px">
                        <input type="color" name="branding_color_primario"
                               value="<?php echo esc_attr($branding['color_primario'] ?: '#007a3d'); ?>">
                        <code style="font-size:11px">{{ color_primario }}</code>
                    </div>
                </label>
                <label style="display:flex;flex-direction:column;gap:4px;font-weight:normal">
                    <?php esc_html_e('Color secundario', 'popup-form-engine'); ?>
                    <div style="display:flex;align-items:center;gap:6px">
                        <input type="color" name="branding_color_secundario"
                               value="<?php echo esc_attr($branding['color_secundario'] ?: '#333333'); ?>">
                        <code style="font-size:11px">{{ color_secundario }}</code>
                    </div>
                </label>
            </div>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="branding_telefono_empresa"><?php esc_html_e('Teléfono', 'popup-form-engine'); ?></label>
        </th>
        <td>
            <input type="text" id="branding_telefono_empresa" name="branding_telefono_empresa" class="regular-text"
                   value="<?php echo esc_attr($branding['telefono_empresa']); ?>"
                   placeholder="+34 900 000 000">
            <p class="description"><code>{{ telefono_empresa }}</code></p>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="branding_email_empresa"><?php esc_html_e('Email de contacto', 'popup-form-engine'); ?></label>
        </th>
        <td>
            <input type="email" id="branding_email_empresa" name="branding_email_empresa" class="regular-text"
                   value="<?php echo esc_attr($branding['email_empresa']); ?>"
                   placeholder="info@miempresa.com">
            <p class="description"><code>{{ email_empresa }}</code></p>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="branding_aviso_legal"><?php esc_html_e('Aviso legal', 'popup-form-engine'); ?></label>
        </th>
        <td>
            <textarea id="branding_aviso_legal" name="branding_aviso_legal" rows="6"
                      class="large-text"><?php echo wp_kses_post($branding['aviso_legal']); ?></textarea>
            <p class="description">
                <?php esc_html_e('Se puede usar HTML. Aparece en el pie del email.', 'popup-form-engine'); ?>
                <code>{{ aviso_legal }}</code>
            </p>
        </td>
    </tr>
</table>
