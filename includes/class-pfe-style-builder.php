<?php
declare(strict_types=1);

namespace PopupFormEngine;

defined('ABSPATH') || exit;

class StyleBuilder {

    public static function buildFormCss(string $slug, array $form): string {
        if (empty($form['styles_enabled'])) {
            return '';
        }

        $primaryColor = sanitize_hex_color($form['style_primary_color'] ?? '') ?? '';
        $btnTextColor = sanitize_hex_color($form['style_button_text_color'] ?? '') ?? '';
        $cardBgColor  = sanitize_hex_color($form['style_card_bg_color'] ?? '') ?? '';
        $opacity      = self::sanitizeOpacity((string) ($form['style_overlay_opacity'] ?? ''));
        $customCss    = wp_strip_all_tags((string) ($form['style_custom_css'] ?? ''));

        if ($primaryColor === '' && $btnTextColor === '' && $cardBgColor === '' && $opacity === '' && trim($customCss) === '') {
            return '';
        }

        $scope = '.pfe-overlay[data-pfe-form="' . esc_attr($slug) . '"]';
        $css   = '';

        $varLines = [];
        if ($primaryColor !== '') $varLines[] = "\t--pfe-green: {$primaryColor};";
        if ($opacity !== '')      $varLines[] = "\tbackground: rgba(0,0,0,{$opacity});";

        if ($varLines) {
            $css .= $scope . " {\n" . implode("\n", $varLines) . "\n}\n";
        }

        if ($cardBgColor !== '') {
            $css .= $scope . " .pfe-card {\n\tbackground: {$cardBgColor};\n}\n";
        }

        if ($btnTextColor !== '') {
            $css .= $scope . " .pfe-submit-btn {\n\tcolor: {$btnTextColor};\n}\n";
        }

        $scoped = self::scopeCustomCss(trim($customCss), $scope);
        if ($scoped !== '') {
            $css .= $scoped . "\n";
        }

        return $css;
    }

    private static function sanitizeOpacity(string $val): string {
        if ($val === '') return '';
        $f = (float) $val;
        if ($f < 0.0 || $f > 1.0) return '';
        return number_format($f, 2);
    }

    /**
     * Prefixes all top-level selectors in $css with $scope.
     * Handles @media/@supports nesting recursively; passes @keyframes verbatim.
     * Strips CSS comments before processing.
     */
    private static function scopeCustomCss(string $css, string $scope): string {
        if (trim($css) === '') return '';

        $css = (string) preg_replace('/\/\*[\s\S]*?\*\//', '', $css);
        $out = '';
        $pos = 0;
        $len = strlen($css);

        while ($pos < $len) {
            $bracePos = strpos($css, '{', $pos);
            if ($bracePos === false) break;

            $selector = trim(substr($css, $pos, $bracePos - $pos));

            // Find the matching closing brace.
            $depth = 1;
            $j     = $bracePos + 1;
            while ($j < $len && $depth > 0) {
                if ($css[$j] === '{') $depth++;
                elseif ($css[$j] === '}') $depth--;
                $j++;
            }

            $blockContent = substr($css, $bracePos + 1, $j - 1 - ($bracePos + 1));
            $pos          = $j;

            if ($selector === '') continue;

            if (preg_match('/^@(keyframes|font-face)\b/i', $selector)) {
                $out .= $selector . " {\n" . $blockContent . "}\n";
            } elseif ($selector[0] === '@') {
                $inner = self::scopeCustomCss($blockContent, $scope);
                $out  .= $selector . " {\n" . $inner . "}\n";
            } else {
                $out .= self::prefixSelectors($selector, $scope) . ' {' . $blockContent . "}\n";
            }
        }

        return $out;
    }

    private static function prefixSelectors(string $selectors, string $scope): string {
        return implode(', ', array_map(
            fn(string $sel): string => $scope . ' ' . trim($sel),
            array_filter(array_map('trim', explode(',', $selectors)))
        ));
    }
}
