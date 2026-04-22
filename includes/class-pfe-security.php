<?php
declare(strict_types=1);

namespace PopupFormEngine;

defined('ABSPATH') || exit;

class Security {

    // NOTE: No REST nonce is used on frontend POST endpoints.
    // wp_create_nonce() ties nonce validity to user session cookies,
    // which is unreliable for anonymous visitors on cached pages.
    // Security is enforced via: honeypot + time-trap + rate limit +
    // origin/referer check + slug existence + server-side field sanitization.
    // This is the same approach used by Contact Form 7, WPForms, etc.

    public function isValidOrigin(\WP_REST_Request $request): bool {
        $origin  = $request->get_header('origin')  ?? '';
        $referer = $request->get_header('referer') ?? '';
        $ourHost = (string) parse_url(home_url(), PHP_URL_HOST);

        foreach ([$origin, $referer] as $h) {
            if ($h === '') continue;
            $host = (string) parse_url($h, PHP_URL_HOST);
            if ($host === $ourHost) return true;
        }
        return false;
    }

    public function isHoneypot(array $params): bool {
        return isset($params['_pfe_hp']) && $params['_pfe_hp'] !== '';
    }

    public function isTimeTrap(array $params, int $minSeconds = 2): bool {
        $ts = isset($params['_pfe_ts']) ? (int) $params['_pfe_ts'] : 0;
        if ($ts === 0) return false;
        return (time() - $ts) < $minSeconds;
    }

    public function getClientIp(): string {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $key) {
            $val = $_SERVER[$key] ?? '';
            if ($val === '') continue;
            $ip = trim(explode(',', $val)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
        return '0.0.0.0';
    }

    public function validatePdfUrl(string $url): bool {
        $urlHost  = parse_url($url, PHP_URL_HOST);
        $siteHost = parse_url(home_url(), PHP_URL_HOST);
        return $urlHost !== null && $urlHost === $siteHost;
    }
}
