<?php

require_once __DIR__ . '/../config/Database.php';

class RoadHazard {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    public function listAll() {
        $q = 'SELECT rh.*, u.full_name AS created_by_name
              FROM road_hazards rh
              LEFT JOIN users u ON rh.created_by = u.id
              ORDER BY rh.created_at DESC';
        $res = $this->conn->query($q);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function save($data, $user_id) {
        $type = in_array($data['hazard_type'] ?? '', ['landslide', 'flood', 'blocked_road'], true)
            ? $data['hazard_type'] : 'blocked_road';
        $lat = (float) ($data['latitude'] ?? 0);
        $lon = (float) ($data['longitude'] ?? 0);
        $label = trim((string) ($data['label'] ?? ''));
        $id = (int) ($data['id'] ?? 0);

        if ($lat === 0.0 && $lon === 0.0) {
            return ['success' => false, 'message' => 'Valid coordinates required'];
        }

        if ($id > 0) {
            $stmt = $this->conn->prepare(
                'UPDATE road_hazards SET hazard_type = ?, latitude = ?, longitude = ?, label = ? WHERE id = ?'
            );
            $stmt->bind_param('sddsi', $type, $lat, $lon, $label, $id);
            $stmt->execute();
            return ['success' => true, 'message' => 'Hazard updated', 'id' => $id];
        }

        $stmt = $this->conn->prepare(
            'INSERT INTO road_hazards (hazard_type, latitude, longitude, label, created_by) VALUES (?, ?, ?, ?, ?)'
        );
        $uid = (int) $user_id;
        $stmt->bind_param('sddsi', $type, $lat, $lon, $label, $uid);
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Hazard recorded', 'id' => $this->conn->insert_id];
        }
        return ['success' => false, 'message' => 'Could not save hazard'];
    }

    public function delete($id) {
        $id = (int) $id;
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid id'];
        }
        $stmt = $this->conn->prepare('DELETE FROM road_hazards WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->affected_rows > 0
            ? ['success' => true, 'message' => 'Hazard removed']
            : ['success' => false, 'message' => 'Hazard not found'];
    }
}
