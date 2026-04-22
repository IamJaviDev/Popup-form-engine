<?php
declare(strict_types=1);

namespace PopupFormEngine;

defined('ABSPATH') || exit;

class Logger {

    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'popup_form_engine_logs';
    }

    public function insert(array $data): void {
        global $wpdb;
        $wpdb->insert(
            $this->table,
            [
                'created_at'          => current_time('mysql'),
                'ip'                  => $data['ip'] ?? '',
                'user_agent'          => substr((string) ($data['user_agent'] ?? ''), 0, 500),
                'flow_type'           => $data['flow_type'] ?? 'generic',
                'form_identifier'     => $data['form_identifier'] ?? null,
                'email'               => $data['email'] ?? null,
                'payload_json'        => isset($data['payload']) ? wp_json_encode($data['payload']) : null,
                'consent_status'      => $data['consent_status'] ?? 'no_aplica',
                'newsletter_sent'     => (int) ($data['newsletter_sent'] ?? 0),
                'newsletter_response' => $data['newsletter_response'] ?? null,
                'email_sent'          => (int) ($data['email_sent'] ?? 0),
                'status'              => $data['status'] ?? 'ok',
                'error_message'       => $data['error_message'] ?? null,
            ],
            ['%s','%s','%s','%s','%s','%s','%s','%s','%d','%s','%d','%s','%s']
        );
    }

    public function getLogs(array $filters = [], int $page = 1, int $per_page = 25): array {
        global $wpdb;
        $where = ['1=1'];
        $values = [];
        if (!empty($filters['flow_type']))      { $where[] = 'flow_type = %s';      $values[] = $filters['flow_type']; }
        if (!empty($filters['consent_status'])) { $where[] = 'consent_status = %s'; $values[] = $filters['consent_status']; }
        if (!empty($filters['date_from']))      { $where[] = 'created_at >= %s';    $values[] = $filters['date_from'] . ' 00:00:00'; }
        if (!empty($filters['date_to']))        { $where[] = 'created_at <= %s';    $values[] = $filters['date_to']   . ' 23:59:59'; }

        $where_sql = implode(' AND ', $where);
        $offset    = ($page - 1) * $per_page;

        if ($values) {
            $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}", ...$values));
            $rows  = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                ...array_merge($values, [$per_page, $offset])
            ), ARRAY_A);
        } else {
            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
            $rows  = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page, $offset
            ), ARRAY_A);
        }
        return ['rows' => $rows ?: [], 'total' => $count];
    }

    public function deleteBefore(int $days): int {
        global $wpdb;
        return (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", $days
        ));
    }

    public function exportCsv(array $filters = []): void {
        $data = $this->getLogs($filters, 1, 100000);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="pfe-logs-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        if (!empty($data['rows'])) {
            fputcsv($out, array_keys($data['rows'][0]));
            foreach ($data['rows'] as $row) { fputcsv($out, $row); }
        }
        fclose($out);
        exit;
    }
}
