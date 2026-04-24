<?php
declare(strict_types=1);

namespace PopupFormEngine;

defined('ABSPATH') || exit;

class Settings {

    public function getGeneral(): array {
        return (array) get_option('pfe_general', []);
    }

    public function saveGeneral(array $data): void {
        update_option('pfe_general', $data);
    }

    public function getPdfNewsletter(): array {
        return (array) get_option('pfe_pdf_newsletter', []);
    }

    public function savePdfNewsletter(array $data): void {
        update_option('pfe_pdf_newsletter', $data);
    }

    public function getNewsletter(): array {
        return (array) get_option('pfe_newsletter', []);
    }

    public function saveNewsletter(array $data): void {
        update_option('pfe_newsletter', $data);
    }

    public function getNewsletterEndpoint(): string {
        $cfg = $this->getNewsletter();

        // Priority 1 — legacy full URL stored under 'endpoint' key (very old installs).
        if (!empty($cfg['endpoint'])) {
            return (string) $cfg['endpoint'];
        }

        // Priority 2 — current format: only the recipient host is configurable.
        // Protocol (https) and path (/newsletter/subscribe) are fixed by product decision.
        $host = trim((string) ($cfg['host'] ?? ''));
        if ($host !== '') {
            return 'https://' . $host . '/newsletter/subscribe';
        }

        // Priority 3 — previous format with separate protocol/port/path fields.
        // Kept so installs that saved those fields before this sprint keep working
        // until the admin is opened and saved again with the new form.
        $protocol = in_array($cfg['protocol'] ?? '', ['http', 'https'], true) ? $cfg['protocol'] : 'https';
        $siteHost = (string) parse_url(home_url(), PHP_URL_HOST);
        $port     = (int) ($cfg['port'] ?? 63443);
        $path     = '/' . ltrim((string) ($cfg['path'] ?? '/newsletter/subscribe'), '/');
        return "{$protocol}://{$siteHost}:{$port}{$path}";
    }

    public function isNewsletterEnabled(): bool {
        return !empty($this->getNewsletter()['enabled']);
    }

    public function getForms(): array {
        return (array) get_option('pfe_forms', []);
    }

    public function saveForms(array $forms): void {
        update_option('pfe_forms', $forms);
    }

    public function getFormBySlug(string $slug): ?array {
        foreach ($this->getForms() as $form) {
            if (($form['slug'] ?? '') === $slug) {
                return $form;
            }
        }
        return null;
    }

    public function getPdfForms(): array {
        return (array) get_option('pfe_pdf_forms', []);
    }

    public function savePdfForms(array $forms): void {
        update_option('pfe_pdf_forms', $forms);
    }

    public function getPdfFormBySlug(string $slug): ?array {
        foreach ($this->getPdfForms() as $form) {
            if (($form['slug'] ?? '') === $slug) {
                return $form;
            }
        }
        return null;
    }

    public function getPdfTemplates(): array {
        return (array) get_option('pfe_pdf_templates', []);
    }

    public function savePdfTemplates(array $data): void {
        update_option('pfe_pdf_templates', $data);
    }

    /**
     * Current PDF template strategy: PAGE_SLUG_TEMPLATE (legacy mode).
     *
     * The template is resolved from the URL slug of the page where the user
     * clicked the PDF link — NOT from the PDF filename itself.
     * This matches the Tota-form-detecterv2 behaviour exactly.
     *
     * Legacy templates contain hardcoded PDF links and branding; they do not
     * require the {{ pdf }} placeholder to function. str_replace('{{ pdf }}', …)
     * is called anyway and is a no-op if the placeholder is absent.
     *
     * Future strategy: CLICKED_PDF_TEMPLATE — resolves the template from the
     * PDF filename/URL that the user actually clicked. Not implemented yet;
     * the handler must be extended to pass a $pdfUrl-derived key here and
     * a separate mapping table must be added to settings.
     * To add it: introduce a second method resolveTemplatePathByPdf(string $pdfUrl)
     * and a getPdfByUrlTemplates() setting, then switch in PdfHandler based on
     * the strategy selected per-mapping entry.
     */
    /**
     * @deprecated Use resolveTemplate() instead. Kept for backwards compatibility.
     */
    public function resolveTemplatePath(string $pageSlug): string {
        $fallback = PFE_DIR . 'templates/plantilla-base.html';

        // Direct match: templates/<slug>.html
        $direct = PFE_DIR . 'templates/' . $pageSlug . '.html';
        if (file_exists($direct)) return $direct;

        // slug_contains mapping (PAGE_SLUG_TEMPLATE / legacy mode)
        foreach ($this->getPdfTemplates() as $entry) {
            $needle = $entry['slug_contains'] ?? '';
            if ($needle !== '' && str_contains($pageSlug, $needle)) {
                $candidate = PFE_DIR . 'templates/' . basename($entry['template_file'] ?? '');
                if (file_exists($candidate)) return $candidate;
            }
        }

        return $fallback;
    }

    // ── PDF email templates (DB-backed) ──────────────────────────────────────

    public function getPdfEmailTemplates(): array {
        return (array) get_option('pfe_pdf_email_templates', []);
    }

    public function savePdfEmailTemplates(array $templates): void {
        update_option('pfe_pdf_email_templates', $templates);
    }

    public function getPdfEmailTemplateBySlug(string $slug): ?array {
        foreach ($this->getPdfEmailTemplates() as $tpl) {
            if (($tpl['slug'] ?? '') === $slug) return $tpl;
        }
        return null;
    }

    // ── PDF filename mappings ─────────────────────────────────────────────────

    public function getPdfFileMappings(): array {
        return (array) get_option('pfe_pdf_file_mappings', []);
    }

    public function savePdfFileMappings(array $mappings): void {
        update_option('pfe_pdf_file_mappings', $mappings);
    }

    // ── Template resolution cascade ───────────────────────────────────────────

    /**
     * Resolves the best email template for a PDF submission.
     *
     * Returns an array with keys: source, subject, html_body, path, resolved_via
     * or null if nothing is found.
     */
    public function resolveTemplate(string $pageSlug, string $pdfUrl, ?string $explicitSlug = null): ?array {
        $pdfFilename = basename((string) parse_url($pdfUrl, PHP_URL_PATH));

        // Paso 1: slug explícito (data-template-slug del enlace PDF)
        if ($explicitSlug !== null && $explicitSlug !== '') {
            $tpl = $this->getPdfEmailTemplateBySlug($explicitSlug);
            if ($tpl !== null) return $this->buildDbResult($tpl, 'explicit');
        }

        // Paso 2: mapping por filename del PDF
        foreach ($this->getPdfFileMappings() as $mapping) {
            $needle  = (string) ($mapping['filename_contains'] ?? '');
            $tplSlug = (string) ($mapping['template_slug']      ?? '');
            if ($needle === '' || !str_contains($pdfFilename, $needle)) continue;
            if ($tplSlug !== '') {
                $tpl = $this->getPdfEmailTemplateBySlug($tplSlug);
                if ($tpl !== null) return $this->buildDbResult($tpl, 'filename_mapping');
            }
        }

        // Paso 3: template BD cuyo slug coincide exactamente con el page slug
        $tpl = $this->getPdfEmailTemplateBySlug($pageSlug);
        if ($tpl !== null) return $this->buildDbResult($tpl, 'page_direct');

        // Paso 4: mappings por page slug (existentes, enriquecidos con template_slug)
        foreach ($this->getPdfTemplates() as $mapping) {
            $needle = (string) ($mapping['slug_contains'] ?? '');
            if ($needle === '' || !str_contains($pageSlug, $needle)) continue;

            $tplSlug = (string) ($mapping['template_slug'] ?? '');
            if ($tplSlug !== '') {
                $tpl = $this->getPdfEmailTemplateBySlug($tplSlug);
                if ($tpl !== null) return $this->buildDbResult($tpl, 'page_mapping');
            }

            $tplFile = (string) ($mapping['template_file'] ?? '');
            if ($tplFile !== '') {
                $path = PFE_DIR . 'templates/' . basename($tplFile);
                if (file_exists($path)) return $this->buildFileResult($path, 'page_mapping');
            }
        }

        // Paso 5: template BD con slug 'default'
        $tpl = $this->getPdfEmailTemplateBySlug('default');
        if ($tpl !== null) return $this->buildDbResult($tpl, 'default');

        // Paso 6: plantilla-base.html en disco
        $fallback = PFE_DIR . 'templates/plantilla-base.html';
        if (file_exists($fallback)) return $this->buildFileResult($fallback, 'fallback');

        return null;
    }

    private function buildDbResult(array $tpl, string $via): array {
        return [
            'source'       => 'db',
            'subject'      => (string) ($tpl['subject']   ?? ''),
            'html_body'    => (string) ($tpl['html_body'] ?? ''),
            'path'         => null,
            'resolved_via' => $via,
        ];
    }

    private function buildFileResult(string $path, string $via): array {
        return [
            'source'       => 'file',
            'subject'      => null,
            'html_body'    => '',
            'path'         => $path,
            'resolved_via' => $via,
        ];
    }

    // ── Branding ──────────────────────────────────────────────────────────────

    public function getBrandingDefaults(): array {
        return [
            'empresa'          => '',
            'logo'             => '',
            'color_primario'   => '',
            'color_secundario' => '',
            'web'              => '',
            'telefono_empresa' => '',
            'email_empresa'    => '',
            'aviso_legal'      => '',
        ];
    }

    public function getBranding(): array {
        return array_merge($this->getBrandingDefaults(), (array) get_option('pfe_branding', []));
    }

    public function saveBranding(array $data): void {
        update_option('pfe_branding', $data);
    }
}
