<?php
defined('ABSPATH') || exit;

use PopupFormEngine\Settings;

class PFE_AdminPage {

    private const TABS = ['general', 'forms', 'newsletter', 'pdf-templates', 'logs'];

    public function __construct(private Settings $settings) {}

    public function handleSave(): void {
        if (empty($_POST['pfe_save'])) return;
        if (!check_admin_referer('pfe_settings_save', 'pfe_nonce')) return;
        if (!current_user_can('manage_options')) wp_die(__('Sin permisos.', 'popup-form-engine'));

        $tab = sanitize_key($_POST['pfe_tab'] ?? 'general');

        switch ($tab) {
            case 'general':
                $this->settings->saveGeneral([
                    'from_email' => sanitize_email($_POST['from_email'] ?? ''),
                    'from_name'  => sanitize_text_field($_POST['from_name'] ?? ''),
                    'rate_limit' => (int) ($_POST['rate_limit'] ?? 5),
                ]);
                break;
            case 'forms':
                $jsonRaw = isset($_POST['pfe_forms_json']) ? wp_unslash($_POST['pfe_forms_json']) : '';
                if ($jsonRaw === '') break;
                $decoded = json_decode($jsonRaw, true);
                if (!is_array($decoded)) break;
                $forms    = [];
                $renderer = new \PopupFormEngine\FormRenderer();
                foreach ($decoded as $f) {
                    if (!is_array($f)) continue;
                    $mode   = sanitize_key($f['mode'] ?? 'visual');
                    $fields = [];
                    foreach ((array) ($f['fields'] ?? []) as $field) {
                        if (!is_array($field)) continue;
                        // Parse select options from "value:Label" lines (one per line).
                        $optRaw = sanitize_textarea_field($field['options_raw'] ?? '');
                        $opts   = [];
                        foreach (array_filter(array_map('trim', explode("\n", $optRaw))) as $line) {
                            if (str_contains($line, ':')) {
                                [$v, $l] = explode(':', $line, 2);
                                $v = sanitize_text_field(trim($v));
                                $l = sanitize_text_field(trim($l));
                                if ($v !== '') $opts[$v] = $l;
                            } elseif ($line !== '') {
                                $cleaned = sanitize_text_field($line);
                                if ($cleaned !== '') $opts[$cleaned] = $cleaned;
                            }
                        }
                        $fieldName = sanitize_key($field['name'] ?? '');
                        if ($fieldName === '') {
                            $fieldName = sanitize_key($field['label'] ?? '');
                        }
                        if ($fieldName === '') continue; // skip truly unnamed + unlabeled fields
                        $fields[] = [
                            'type'             => sanitize_key($field['type'] ?? 'text'),
                            'name'             => $fieldName,
                            'label'            => sanitize_text_field($field['label'] ?? ''),
                            'placeholder'      => sanitize_text_field($field['placeholder'] ?? ''),
                            'required'         => !empty($field['required']),
                            'options'          => $opts,
                            'is_primary_email' => !empty($field['is_primary_email']),
                        ];
                    }
                    $htmlContent = '';
                    if ($mode === 'html') {
                        $rawHtml = (string) ($f['html_content'] ?? '');
                        if (!$renderer->validateNoNestedForm($rawHtml)) {
                            wp_safe_redirect(add_query_arg([
                                'page' => 'popup-form-engine', 'tab' => 'forms', 'error' => 'nested_form',
                            ], admin_url('admin.php')));
                            exit;
                        }
                        $htmlContent = wp_kses($rawHtml, \PopupFormEngine\FormRenderer::formHtmlAllowedTags());
                    }
                    $slug = sanitize_key($f['slug'] ?? '');
                    if ($slug === '') continue;
                    $forms[] = [
                        'slug'                       => $slug,
                        'title'                      => sanitize_text_field($f['title'] ?? ''),
                        'subtitle'                   => sanitize_text_field($f['subtitle'] ?? ''),
                        'mode'                       => $mode,
                        'fields'                     => $fields,
                        'html_content'               => $htmlContent,
                        'submit_button_text'         => sanitize_text_field($f['submit_button_text'] ?? ''),
                        'success_message'            => sanitize_textarea_field($f['success_message'] ?? ''),
                        'newsletter_enabled'         => !empty($f['newsletter_enabled']),
                        'newsletter_position'        => sanitize_key($f['newsletter_position'] ?? 'before_submit'),
                        'newsletter_label'           => wp_kses_post($f['newsletter_label'] ?? ''),
                        'newsletter_pre_checked'     => !empty($f['newsletter_pre_checked']),
                        'newsletter_required'        => !empty($f['newsletter_required']),
                        'callback_enabled'           => !empty($f['callback_enabled']),
                        'callback_label'             => sanitize_text_field($f['callback_label'] ?? ''),
                        'callback_email_recipients'  => sanitize_textarea_field($f['callback_email_recipients'] ?? ''),
                    ];
                }
                $this->settings->saveForms($forms);

                // ── PDF forms ──────────────────────────────────────────────────────────────
                $pdfJsonRaw = isset($_POST['pfe_pdf_forms_json']) ? wp_unslash($_POST['pfe_pdf_forms_json']) : '';
                if ($pdfJsonRaw !== '') {
                    $pdfDecoded = json_decode($pdfJsonRaw, true);
                    if (is_array($pdfDecoded)) {
                        $pdfForms = [];
                        foreach ($pdfDecoded as $pf) {
                            if (!is_array($pf)) continue;
                            $pdfSlug = sanitize_key($pf['slug'] ?? '');
                            if ($pdfSlug === '') continue;
                            $pdfFields = [];
                            foreach ((array) ($pf['fields'] ?? []) as $field) {
                                if (!is_array($field)) continue;
                                $fName = sanitize_key($field['name'] ?? '');
                                if ($fName === '') $fName = sanitize_key($field['label'] ?? '');
                                if ($fName === '') continue;
                                $pdfFields[] = [
                                    'type'             => sanitize_key($field['type'] ?? 'text'),
                                    'name'             => $fName,
                                    'label'            => sanitize_text_field($field['label'] ?? ''),
                                    'placeholder'      => sanitize_text_field($field['placeholder'] ?? ''),
                                    'required'         => !empty($field['required']),
                                    'is_primary_email' => !empty($field['is_primary_email']),
                                ];
                            }
                            $pdfForms[] = [
                                'slug'                  => $pdfSlug,
                                'title'                 => sanitize_text_field($pf['title'] ?? ''),
                                'success_message'       => sanitize_textarea_field($pf['success_message'] ?? ''),
                                'newsletter_enabled'    => !empty($pf['newsletter_enabled']),
                                'newsletter_label'      => wp_kses_post($pf['newsletter_label'] ?? ''),
                                'newsletter_pre_checked'=> !empty($pf['newsletter_pre_checked']),
                                'fields'                => $pdfFields,
                            ];
                        }
                        $this->settings->savePdfForms($pdfForms);
                    }
                }
                break;
            case 'newsletter':
                $this->settings->saveNewsletter([
                    'enabled' => !empty($_POST['newsletter_enabled']),
                    'host'    => sanitize_text_field($_POST['newsletter_host'] ?? ''),
                    'timeout' => max(1, min(60, (int) ($_POST['newsletter_timeout'] ?? 10))),
                ]);
                break;
            case 'pdf-templates':
                $this->settings->savePdfNewsletter([
                    'enabled' => !empty($_POST['pdf_newsletter_enabled']),
                ]);
                $rawMappings = is_array($_POST['pdf_mappings'] ?? null) ? $_POST['pdf_mappings'] : [];
                $mappings    = [];
                foreach ($rawMappings as $m) {
                    if (!is_array($m)) continue;
                    $sc = sanitize_text_field($m['slug_contains'] ?? '');
                    $tf = sanitize_file_name($m['template_file'] ?? '');
                    if ($sc === '' || $tf === '') continue;
                    // Ensure only a filename, no directory traversal.
                    $tf = basename($tf);
                    if (!preg_match('/\.html?$/i', $tf)) continue;
                    $mappings[] = ['slug_contains' => $sc, 'template_file' => $tf];
                }
                if (!empty($mappings)) {
                    $this->settings->savePdfTemplates($mappings);
                }
                break;
        }

        wp_safe_redirect(add_query_arg([
            'page'  => 'popup-form-engine',
            'tab'   => $tab,
            'saved' => '1',
        ], admin_url('admin.php')));
        exit;
    }

    public function render(): void {
        $this->enqueueAssets();
        $activeTab = sanitize_key($_GET['tab'] ?? 'general');
        if (!in_array($activeTab, self::TABS, true)) $activeTab = 'general';
        $saved = !empty($_GET['saved']);
        $error = sanitize_key($_GET['error'] ?? '');
        ?>
        <div class="wrap pfe-wrap">
            <h1>Popup Form Engine</h1>
            <?php if ($saved): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Ajustes guardados.', 'popup-form-engine'); ?></p></div>
            <?php endif; ?>
            <?php if ($error === 'nested_form'): ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e('El HTML no puede contener etiquetas <form> anidadas.', 'popup-form-engine'); ?></p></div>
            <?php endif; ?>
            <nav class="nav-tab-wrapper">
                <?php foreach (self::TABS as $t): ?>
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'popup-form-engine', 'tab' => $t], admin_url('admin.php'))); ?>"
                       class="nav-tab<?php echo $activeTab === $t ? ' nav-tab-active' : ''; ?>">
                        <?php echo esc_html($this->tabLabel($t)); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <form method="post" action="" id="pfe-settings-form">
                <?php wp_nonce_field('pfe_settings_save', 'pfe_nonce'); ?>
                <input type="hidden" name="pfe_save" value="1">
                <input type="hidden" name="pfe_tab" value="<?php echo esc_attr($activeTab); ?>">
                <?php $this->renderTab($activeTab); ?>
                <?php if ($activeTab !== 'logs' && $activeTab !== 'forms'): ?>
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Guardar cambios', 'popup-form-engine'); ?></button>
                    </p>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }

    private function renderTab(string $tab): void {
        $view = PFE_DIR . 'admin/views/tab-' . $tab . '.php';
        if (file_exists($view)) {
            include $view;
        }
    }

    private function enqueueAssets(): void {
        wp_enqueue_style('pfe-admin', PFE_URL . 'admin/assets/admin.css', [], PFE_VERSION);
        wp_enqueue_script('pfe-admin', PFE_URL . 'admin/assets/admin.js', [], PFE_VERSION, true);
        wp_localize_script('pfe-admin', 'pfeAdmin', [
            'formsData'    => $this->settings->getForms(),
            'pdfFormsData' => $this->settings->getPdfForms(),
            'restUrl'      => esc_url_raw(rest_url('popup-form-engine/v1')),
            'nonce'        => wp_create_nonce('pfe_rest_action'),
        ]);
    }

    private function tabLabel(string $tab): string {
        return match($tab) {
            'general'       => __('General', 'popup-form-engine'),
            'forms'         => __('Formularios', 'popup-form-engine'),
            'newsletter'    => __('Newsletter', 'popup-form-engine'),
            'pdf-templates' => __('PDF / Templates', 'popup-form-engine'),
            'logs'          => __('Logs', 'popup-form-engine'),
            default         => ucfirst($tab),
        };
    }
}
