<?php
declare(strict_types=1);

namespace PopupFormEngine;

defined('ABSPATH') || exit;

class FormRenderer {

    private static array $allowedKses = [
        'b' => [], 'strong' => [], 'em' => [], 'i' => [],
        'a' => ['href' => [], 'target' => [], 'rel' => []],
        'span' => ['class' => [], 'style' => []], 'br' => [],
    ];

    /**
     * kses allowlist for admin-authored HTML form content.
     * Allows form field tags stripped by wp_kses_post while blocking <form>/<script>.
     */
    public static function formHtmlAllowedTags(): array {
        $base = wp_kses_allowed_html('post');

        $shared = [
            'class' => [], 'id' => [], 'style' => [], 'title' => [],
            'aria-label' => [], 'aria-describedby' => [], 'aria-required' => [],
            'aria-invalid' => [], 'aria-hidden' => [], 'aria-live' => [],
            'aria-atomic' => [], 'aria-expanded' => [], 'aria-controls' => [],
            'aria-labelledby' => [], 'aria-checked' => [], 'aria-selected' => [],
            'data-id' => [], 'data-value' => [], 'data-key' => [], 'data-name' => [], 'data-type' => [],
        ];

        $inputAttrs = $shared + [
            'type' => [], 'name' => [], 'value' => [], 'placeholder' => [],
            'required' => [], 'checked' => [], 'disabled' => [], 'readonly' => [],
            'autocomplete' => [], 'minlength' => [], 'maxlength' => [],
            'pattern' => [], 'tabindex' => [],
        ];

        $base['input']    = $inputAttrs;
        $base['label']    = $shared + ['for' => []];
        $base['textarea'] = $shared + [
            'name' => [], 'rows' => [], 'cols' => [], 'placeholder' => [],
            'required' => [], 'disabled' => [], 'readonly' => [],
            'maxlength' => [], 'autocomplete' => [], 'tabindex' => [],
        ];
        $base['select']   = $shared + [
            'name' => [], 'required' => [], 'disabled' => [],
            'multiple' => [], 'autocomplete' => [], 'tabindex' => [],
        ];
        $base['option']   = ['value' => [], 'selected' => [], 'disabled' => [], 'class' => []];
        $base['optgroup'] = ['label' => [], 'disabled' => [], 'class' => []];
        $base['fieldset'] = $shared + ['disabled' => []];
        $base['legend']   = $shared;

        unset($base['form'], $base['script'], $base['iframe'], $base['object'], $base['embed']);

        return $base;
    }

    public function renderVisual(array $formConfig): string {
        $fields        = $formConfig['fields'] ?? [];
        $nlEnabled     = !empty($formConfig['newsletter_enabled']);
        $nlPosition    = $formConfig['newsletter_position'] ?? 'before_submit';
        $submitText    = esc_html($formConfig['submit_button_text'] ?? __('Enviar', 'popup-form-engine'));
        $callbackBlock = !empty($formConfig['callback_enabled']) ? $this->renderCallbackBlock($formConfig) : '';

        $html = '';
        foreach ($fields as $field) {
            $html .= $this->renderField($field);
        }

        $nlCheckbox = $nlEnabled ? $this->renderNewsletterCheckbox($formConfig) : '';
        $traps      = $this->renderHoneypotAndTimeTrap();
        $submitBtn  = '<button type="submit" class="pfe-submit-btn">' . $submitText . '</button>';

        if ($nlPosition === 'before_submit') {
            return $html . $callbackBlock . $nlCheckbox . $traps . $submitBtn;
        }
        return $html . $callbackBlock . $traps . $submitBtn . $nlCheckbox;
    }

    public function renderHtmlMode(array $formConfig, string $rawHtml): string {
        $sanitized     = wp_kses($rawHtml, self::formHtmlAllowedTags());
        $nlEnabled     = !empty($formConfig['newsletter_enabled']);
        $nlPosition    = $formConfig['newsletter_position'] ?? 'before_submit';
        $submitText    = esc_html($formConfig['submit_button_text'] ?? __('Enviar', 'popup-form-engine'));
        $callbackBlock = !empty($formConfig['callback_enabled']) ? $this->renderCallbackBlock($formConfig) : '';

        $submitBtn = '<button type="submit" class="pfe-submit-btn">' . $submitText . '</button>';
        $traps     = $this->renderHoneypotAndTimeTrap();

        if ($nlEnabled && !$this->htmlContainsNewsletterConsent($sanitized)) {
            $nlCheckbox = $this->renderNewsletterCheckbox($formConfig);
            if ($nlPosition === 'after_submit') {
                return $sanitized . $callbackBlock . $traps . $submitBtn . $nlCheckbox;
            }
            return $sanitized . $callbackBlock . $nlCheckbox . $traps . $submitBtn;
        }

        return $sanitized . $callbackBlock . $traps . $submitBtn;
    }

    private function renderCallbackBlock(array $formConfig): string {
        $label = sanitize_text_field($formConfig['callback_label'] ?? '');
        if ($label === '') {
            $label = __('Quiero que me llaméis', 'popup-form-engine');
        }
        return
            '<div class="pfe-callback-block pfe-field-wrap">' .
                '<label class="pfe-callback-label">' .
                    '<input type="checkbox" name="pfe_callback_requested" value="1" class="pfe-callback-trigger"> ' .
                    esc_html($label) .
                '</label>' .
                '<div class="pfe-callback-fields" style="display:none">' .
                    '<div class="pfe-field-wrap">' .
                        '<label for="pfe-cb-day">' . esc_html__('Día preferido', 'popup-form-engine') .
                        ' <span class="pfe-required" aria-hidden="true">*</span></label>' .
                        '<input type="text" id="pfe-cb-day" name="pfe_callback_day" class="pfe-input"' .
                        ' placeholder="' . esc_attr__('Ej: Lunes o 15 de marzo', 'popup-form-engine') . '">' .
                    '</div>' .
                    '<div class="pfe-field-wrap">' .
                        '<label for="pfe-cb-time">' . esc_html__('Hora preferida', 'popup-form-engine') .
                        ' <span class="pfe-required" aria-hidden="true">*</span></label>' .
                        '<input type="text" id="pfe-cb-time" name="pfe_callback_time" class="pfe-input"' .
                        ' placeholder="' . esc_attr__('Ej: 10:00 - 12:00', 'popup-form-engine') . '">' .
                    '</div>' .
                '</div>' .
            '</div>';
    }

    public function validateNoNestedForm(string $html): bool {
        if (trim($html) === '') return true;
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<html><body>' . $html . '</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        return $doc->getElementsByTagName('form')->length === 0;
    }

    private function renderField(array $field): string {
        $type        = sanitize_key($field['type'] ?? 'text');
        $name        = sanitize_key($field['name'] ?? '');
        $label       = esc_html($field['label'] ?? '');
        $placeholder = esc_attr($field['placeholder'] ?? '');
        $required    = !empty($field['required']);
        $reqAttr     = $required ? ' required' : '';
        $reqMark     = $required ? ' <span class="pfe-required" aria-hidden="true">*</span>' : '';
        if ($name === '') return '';
        $id = 'pfe-field-' . $name;
        switch ($type) {
            case 'textarea':
                return sprintf(
                    '<div class="pfe-field-wrap"><label for="%1$s">%2$s%3$s</label><textarea id="%1$s" name="%4$s" placeholder="%5$s" class="pfe-input"%6$s></textarea></div>',
                    esc_attr($id), $label, $reqMark, esc_attr($name), $placeholder, $reqAttr
                );
            case 'select':
                $options = '';
                foreach ((array) ($field['options'] ?? []) as $val => $text) {
                    $options .= sprintf('<option value="%s">%s</option>', esc_attr($val), esc_html($text));
                }
                return sprintf(
                    '<div class="pfe-field-wrap"><label for="%1$s">%2$s%3$s</label><select id="%1$s" name="%4$s" class="pfe-input"%5$s>%6$s</select></div>',
                    esc_attr($id), $label, $reqMark, esc_attr($name), $reqAttr, $options
                );
            case 'checkbox':
                return sprintf(
                    '<div class="pfe-field-wrap pfe-field-checkbox"><label><input type="checkbox" name="%1$s" value="1"%2$s> %3$s</label></div>',
                    esc_attr($name), $reqAttr, $label
                );
            default:
                $inputType = in_array($type, ['text','email','tel'], true) ? $type : 'text';
                return sprintf(
                    '<div class="pfe-field-wrap"><label for="%1$s">%2$s%3$s</label><input type="%7$s" id="%1$s" name="%4$s" placeholder="%5$s" class="pfe-input"%6$s></div>',
                    esc_attr($id), $label, $reqMark, esc_attr($name), $placeholder, $reqAttr, $inputType
                );
        }
    }

    private function renderNewsletterCheckbox(array $formConfig): string {
        $label      = wp_kses($formConfig['newsletter_label'] ?? __('Quiero recibir novedades por email', 'popup-form-engine'), self::$allowedKses);
        $preChecked = !empty($formConfig['newsletter_pre_checked']) ? ' checked' : '';
        $required   = !empty($formConfig['newsletter_required']) ? ' required' : '';
        return sprintf(
            '<div class="pfe-field-wrap pfe-newsletter-consent"><label><input type="checkbox" name="pfe_newsletter_consent" value="1"%1$s%2$s> %3$s</label></div>',
            $preChecked, $required, $label
        );
    }

    private function renderHoneypotAndTimeTrap(): string {
        return '<input type="text" name="_pfe_hp" value="" autocomplete="off" aria-hidden="true" tabindex="-1" style="position:absolute;left:-9999px;width:1px;height:1px;">'
            . '<input type="hidden" name="_pfe_ts" value="" data-pfe-ts="1">';
    }

    private function htmlContainsNewsletterConsent(string $html): bool {
        return str_contains($html, 'name="pfe_newsletter_consent"')
            || str_contains($html, "name='pfe_newsletter_consent'");
    }
}
