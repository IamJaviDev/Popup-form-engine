<?php
declare(strict_types=1);

namespace PopupFormEngine;

defined('ABSPATH') || exit;

class NewsletterClient {

    public function __construct(private Settings $settings) {}

    /** @return array{sent:bool,response:string} */
    public function send(array $payload): array {
        if (!$this->settings->isNewsletterEnabled()) {
            return ['sent' => false, 'response' => 'newsletter_disabled'];
        }
        $cfg      = $this->settings->getNewsletter();
        $payload['source_domain'] = (string) parse_url(home_url(), PHP_URL_HOST);
        $response = wp_remote_post($this->settings->getNewsletterEndpoint(), [
            'timeout'   => (int) ($cfg['timeout'] ?? 10),
            'headers'   => ['Content-Type' => 'application/json'],
            'body'      => wp_json_encode($payload),
            'sslverify' => true,
        ]);
        if (is_wp_error($response)) {
            return ['sent' => false, 'response' => $response->get_error_message()];
        }
        $code = wp_remote_retrieve_response_code($response);
        return [
            'sent'     => $code >= 200 && $code < 300,
            'response' => substr((string) wp_remote_retrieve_body($response), 0, 500),
        ];
    }
}
