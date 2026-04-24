<?php
declare(strict_types=1);

namespace PopupFormEngine;

defined('ABSPATH') || exit;

final class Plugin {

    private static ?self $instance = null;

    private Settings         $settings;
    private Security         $security;
    private RateLimiter      $rateLimiter;
    private NewsletterClient $newsletter;
    private Logger           $logger;
    private FormRenderer     $formRenderer;
    private PdfHandler       $pdfHandler;
    private FormHandler      $formHandler;
    private RestController   $rest;

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if (get_option('pfe_db_version') !== PFE_VERSION) {
            (new Installer())->run();
        }

        $this->settings     = new Settings();
        $this->security     = new Security();
        $this->rateLimiter  = new RateLimiter();
        $this->newsletter   = new NewsletterClient($this->settings);
        $this->logger       = new Logger();
        $this->formRenderer = new FormRenderer();
        $this->pdfHandler   = new PdfHandler($this->settings, $this->newsletter, $this->logger, $this->security);
        $this->formHandler  = new FormHandler($this->settings, $this->newsletter, $this->logger);
        $this->rest         = new RestController(
            $this->settings, $this->security, $this->rateLimiter,
            $this->pdfHandler, $this->formHandler,
            $this->logger, $this->formRenderer
        );

        add_action('rest_api_init',      [$this->rest, 'register']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontend']);

        if (is_admin()) {
            new \PFE_Admin($this->settings);
        }
    }

    public function enqueueFrontend(): void {
        wp_enqueue_style(
            'pfe-popup-front',
            PFE_URL . 'assets/css/popup-front.css',
            [],
            PFE_VERSION
        );
        wp_enqueue_script(
            'pfe-popup-front',
            PFE_URL . 'assets/js/popup-front.js',
            [],
            PFE_VERSION,
            true
        );
        wp_localize_script('pfe-popup-front', 'pfeData', [
            'restUrl' => esc_url_raw(rest_url('popup-form-engine/v1/')),
        ]);

        $customCss = '';
        foreach ($this->settings->getForms() as $form) {
            $slug = sanitize_key($form['slug'] ?? '');
            if ($slug !== '') $customCss .= StyleBuilder::buildFormCss($slug, $form);
        }
        foreach ($this->settings->getPdfForms() as $form) {
            $slug = sanitize_key($form['slug'] ?? '');
            if ($slug !== '') $customCss .= StyleBuilder::buildFormCss($slug, $form);
        }
        if ($customCss !== '') {
            wp_add_inline_style('pfe-popup-front', $customCss);
        }
    }
}
