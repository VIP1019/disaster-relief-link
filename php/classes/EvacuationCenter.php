<?php
/**
 * Evacuation / shelter sites (per barangay).
 */

require_once __DIR__ . '/../config/Database.php';

class EvacuationCenter {
    private $conn;
    private $table = 'evacuation_centers';

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    public function listAll($barangay_id = null) {
        $q = "SELECT ec.*, b.name AS barangay_name
              FROM {$this->table} ec
              INNER JOIN barangays b ON ec.barangay_id = b.id
              WHERE 1=1";
        if ($barangay_id !== null && $barangay_id !== '') {
            $q .= ' AND ec.barangay_id = ?';
        }
        $q .= ' ORDER BY b.name, ec.center_name';
        $stmt = $this->conn->prepare($q);
        if ($barangay_id !== null && $barangay_id !== '') {
            $bid = (int) $barangay_id;
            $stmt->bind_param('i', $bid);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getById($id) {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }
        $stmt = $this->conn->prepare(
            "SELECT ec.*, b.name AS barangay_name FROM {$this->table} ec
             INNER JOIN barangays b ON ec.barangay_id = b.id WHERE ec.id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function create($barangay_id, $center_name, $address, $capacity, $current_occupancy, $contact_person, $contact_phone, $facilities, $status, $latitude, $longitude) {
        $bid = (int) $barangay_id;
        $cap = (int) $capacity;
        $occ = (int) $current_occupancy;
        $name = trim((string) $center_name);
        if ($bid <= 0 || $name === '' || $cap < 0 || $occ < 0) {
            return ['success' => false, 'message' => 'Required fields missing'];
        }
        $st = in_array($status, ['open', 'full', 'closed'], true) ? $status : 'open';
        $lat = $latitude === null || $latitude === '' ? 0.0 : (float) $latitude;
        $lon = $longitude === null || $longitude === '' ? 0.0 : (float) $longitude;

        $q = "INSERT INTO {$this->table} (barangay_id, center_name, address, capacity, current_occupancy, contact_person, contact_phone, facilities, status, latitude, longitude)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($q);
        $stmt->bind_param(
            'issiissssdd',
            $bid,
            $name,
            $address,
            $cap,
            $occ,
            $contact_person,
            $contact_phone,
            $facilities,
            $st,
            $lat,
            $lon
        );
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Evacuation center created', 'id' => $this->conn->insert_id];
        }
        return ['success' => false, 'message' => 'Create failed: ' . $this->conn->error];
    }

    public function update($id, $barangay_id, $center_name, $address, $capacity, $current_occupancy, $contact_person, $contact_phone, $facilities, $status, $latitude, $longitude) {
        $id = (int) $id;
        $bid = (int) $barangay_id;
        $name = trim((string) $center_name);
        if ($id <= 0 || $bid <= 0 || $name === '') {
            return ['success' => false, 'message' => 'Invalid data'];
        }
        $cap = (int) $capacity;
        $occ = (int) $current_occupancy;
        $st = in_array($status, ['open', 'full', 'closed'], true) ? $status : 'open';
        $lat = $latitude === null || $latitude === '' ? 0.0 : (float) $latitude;
        $lon = $longitude === null || $longitude === '' ? 0.0 : (float) $longitude;

        $q = "UPDATE {$this->table} SET barangay_id=?, center_name=?, address=?, capacity=?, current_occupancy=?, contact_person=?, contact_phone=?, facilities=?, status=?, latitude=?, longitude=?
              WHERE id=?";
        $stmt = $this->conn->prepare($q);
        $stmt->bind_param(
            'issiissssddi',
            $bid,
            $name,
            $address,
            $cap,
            $occ,
            $contact_person,
            $contact_phone,
            $facilities,
            $st,
            $lat,
            $lon,
            $id
        );
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Evacuation center updated'];
        }
        return ['success' => false, 'message' => 'Update failed'];
    }

    public function delete($id) {
        $id = (int) $id;
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid id'];
        }
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            return ['success' => true, 'message' => 'Deleted'];
        }
        return ['success' => false, 'message' => 'Not found'];
    }
}
