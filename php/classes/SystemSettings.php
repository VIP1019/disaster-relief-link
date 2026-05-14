<?php

require_once __DIR__ . '/../config/Database.php';

/**
 * Simple key/value settings (emergency broadcast banner, etc.).
 */
class SystemSettings {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    public function get(string $key, ?string $default = null): ?string {
        $stmt = $this->conn->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            return $default;
        }
        return $row['setting_value'];
    }

    public function set(string $key, string $value): bool {
        $stmt = $this->conn->prepare(
            'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->bind_param('ss', $key, $value);
        return $stmt->execute();
    }

    /**
     * @return array{active: bool, title: string, body: string, protocol_url: string}
     */
    public function getEmergencyBroadcast(): array {
        $active = $this->get('emergency_broadcast_active', '0') === '1';
        return [
            'active' => $active,
            'title' => (string) ($this->get('emergency_broadcast_title', '') ?? ''),
            'body' => (string) ($this->get('emergency_broadcast_body', '') ?? ''),
            'protocol_url' => (string) ($this->get('emergency_broadcast_protocol_url', '') ?? ''),
        ];
    }

    /**
     * @param array{active?: bool, title?: string, body?: string, protocol_url?: string} $data
     */
    public function setEmergencyBroadcast(array $data): bool {
        if (isset($data['active'])) {
            $this->set('emergency_broadcast_active', !empty($data['active']) ? '1' : '0');
        }
        if (array_key_exists('title', $data)) {
            $this->set('emergency_broadcast_title', (string) $data['title']);
        }
        if (array_key_exists('body', $data)) {
            $this->set('emergency_broadcast_body', (string) $data['body']);
        }
        if (array_key_exists('protocol_url', $data)) {
            $this->set('emergency_broadcast_protocol_url', (string) $data['protocol_url']);
        }
        return true;
    }
}
