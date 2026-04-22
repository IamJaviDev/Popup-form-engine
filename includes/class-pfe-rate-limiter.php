<?php
declare(strict_types=1);

namespace PopupFormEngine;

defined('ABSPATH') || exit;

class RateLimiter {

    public function isAllowed(string $ip, int $maxPerHour): bool {
        return (int) get_transient('pfe_rl_' . $ip) < $maxPerHour;
    }

    public function increment(string $ip): void {
        $key   = 'pfe_rl_' . $ip;
        $count = (int) get_transient($key);
        set_transient($key, $count + 1, HOUR_IN_SECONDS);
    }
}
